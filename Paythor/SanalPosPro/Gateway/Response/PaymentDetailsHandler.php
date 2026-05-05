<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class PaymentDetailsHandler implements HandlerInterface
{
    private const DETAIL_KEYS = [
        'paythor_status',
        'paythor_payment_id',
        'gateway_name',
        'card_last_four',
        'card_brand',
        'installment_count',
        'auth_code',
        'error_code',
        'error_message',
    ];

    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();

        foreach (self::DETAIL_KEYS as $key) {
            if (isset($response[$key]) && $response[$key] !== '') {
                $payment->setAdditionalInformation($key, $response[$key]);
            }
        }
    }
}
