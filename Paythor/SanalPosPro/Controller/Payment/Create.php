<?php
/**
 * File: app/code/Paythor/SanalPosPro/Controller/Payment/Create.php
 *
 * Custom AJAX endpoint hit by sanalpospro-method.js INSTEAD of the
 * standard Magento REST endpoint /V1/carts/mine/payment-information.
 *
 * Responsibilities:
 *   1. Validate session + form key (CSRF).
 *   2. Force the quote's payment method to paythor_sanalpospro.
 *   3. Call PaythorAdapter::createPaymentFromQuote() to obtain the iframe HTML.
 *   4. Store the pending quote ID in the checkout session for Confirm.php to verify.
 *   5. Return JSON { success, iframe_html, quote_id }.
 *
 * The Magento order is NOT created here. It is created in Confirm.php only after
 * the customer completes payment in the Paythor iframe, preserving the cart
 * if the customer abandons or goes back.
 */
declare(strict_types=1);

namespace Paythor\SanalPosPro\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Paythor\SanalPosPro\Model\Api\PaythorAdapter;
use Paythor\SanalPosPro\Model\Config\PaymentConfig;
use Psr\Log\LoggerInterface;

class Create implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PaythorAdapter $paythorAdapter,
        private readonly PaymentConfig $paymentConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        // -- 1. CSRF / form key -----------------------------------------------
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page.')->render(),
            ]);
        }

        // -- 2. Module operational? -------------------------------------------
        if (!$this->paymentConfig->isOperational()) {
            return $result->setHttpResponseCode(503)->setData([
                'success' => false,
                'message' => __('Payment method is not configured.')->render(),
            ]);
        }

        try {
            // -- 3. Load quote from session -----------------------------------
            $quote = $this->checkoutSession->getQuote();

            if (!$this->isValidQuote($quote)) {
                $cartId = trim((string)$this->request->getParam('cart_id', ''));
                if ($cartId !== '') {
                    $quote = $this->loadQuoteByCartId($cartId);
                }
            }

            if (!$this->isValidQuote($quote)) {
                throw new LocalizedException(__('Your cart is empty or invalid.'));
            }

            // -- 4. Stamp payment method on the quote (no order yet) ----------
            // Always start a fresh payment attempt for this quote. If the customer
            // abandoned a previous iframe, stale process/transaction metadata can be
            // copied into a later placeOrder() run and cause gateway collisions.
            $payment = $quote->getPayment();
            $payment->setMethod(PaymentConfig::METHOD_CODE);
            $payment->setLastTransId(null);
            $payment->unsAdditionalInformation('paythor_process_token');
            $payment->unsAdditionalInformation('paythor_transaction_id');
            $payment->unsAdditionalInformation('paythor_status');

            // Force a new merchant reference for every create attempt.
            // Reusing the same reserved_order_id after an abandoned attempt can
            // make Paythor treat the request as an update to an old payment.
            $oldReservedOrderId = (string)$quote->getReservedOrderId();
            $quote->setReservedOrderId(null);
            $quote->reserveOrderId();
            $newReservedOrderId = (string)$quote->getReservedOrderId();
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            if ($oldReservedOrderId !== '' && $oldReservedOrderId !== $newReservedOrderId) {
                $this->logger->info('Paythor Create: reserved_order_id rotated for new payment attempt', [
                    'quote_id'        => (int)$quote->getId(),
                    'old_reference'   => $oldReservedOrderId,
                    'new_reference'   => $newReservedOrderId,
                ]);
            }

            // -- 5. Request payment link from Paythor -------------------------
            $callbackUrl = $this->urlBuilder->getUrl('paythor/payment/callback', ['_secure' => true]);
            $remoteIp    = (string)($this->request->getServer('REMOTE_ADDR') ?? '');

            $payment = $this->paythorAdapter->createPaymentFromQuote($quote, $callbackUrl, $remoteIp);

            // -- 6. Store pending quote ID so Confirm.php can verify session --
            // Clear any stale PaythorCreatedOrderId from a previous payment so the
            // Callback guard doesn't fire against the wrong order.
            $this->checkoutSession->unsPaythorCreatedOrderId();
            $this->checkoutSession->setPaythorPendingQuoteId((int)$quote->getId());

            return $result->setData([
                'success'     => true,
                'iframe_html' => $payment['iframe_html'],
                'quote_id'    => (string)$quote->getId(),
            ]);

        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Paythor Create controller failure', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('We could not start the payment. Please try again.')->render(),
            ]);
        }
    }

    private function isValidQuote(?CartInterface $quote): bool
    {
        return $quote !== null
            && (int)$quote->getId() > 0
            && (float)$quote->getGrandTotal() > 0.0
            && (int)$quote->getItemsCount() > 0;
    }

    private function loadQuoteByCartId(string $cartId): ?CartInterface
    {
        try {
            $mask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            if ($mask->getQuoteId()) {
                return $this->cartRepository->getActive((int)$mask->getQuoteId());
            }
            if (ctype_digit($cartId)) {
                return $this->cartRepository->getActive((int)$cartId);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Paythor: unable to resolve quote by cart_id', [
                'cart_id' => $cartId,
                'message' => $e->getMessage(),
            ]);
        }
        return null;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }
}
