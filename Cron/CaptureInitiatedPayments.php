<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Cron;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Sanalpospro\SanalPosPro\Model\Api\PaythorAdapter;
use Sanalpospro\SanalPosPro\Model\Config\PaymentConfig;
use Psr\Log\LoggerInterface;

/**
 * Cron job: Retroactively captures payments that are still in "Initiated" state.
 *
 * Scheduled every 15 minutes. Queries all PROCESSING orders paid via
 * paythor_sanalpospro that have a stored process token, and calls the
 * Paythor capture API for each one that hasn't been captured yet.
 *
 * This acts as a safety net for orders whose browser callback or
 * server webhook was not received.
 */
class CaptureInitiatedPayments
{
    /**
     *
     * @param PaythorAdapter $paythorAdapter
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param PaymentConfig $paymentConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaythorAdapter $paythorAdapter,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly PaymentConfig $paymentConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Magento cron entry point — no arguments, public visibility.
     */
    public function execute(): void
    {
        if (!$this->paymentConfig->isConnected()) {
            return;
        }

        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('state', Order::STATE_PROCESSING)
            ->addFieldToFilter('status', ['neq' => 'closed']);

        // Join payment table to restrict to Paythor orders only.
        $collection->getSelect()->joinInner(
            ['sop' => $collection->getResource()->getTable('sales_order_payment')],
            'sop.parent_id = main_table.entity_id AND sop.method = \'' . PaymentConfig::METHOD_CODE . '\'',
            []
        );

        $collection->setOrder('entity_id', 'ASC');

        $count = 0;

        /** @var Order $order */
        foreach ($collection as $order) {
            $payment      = $order->getPayment();
            $processToken = (string)($payment->getAdditionalInformation('paythor_process_token') ?? '');

            if ($processToken === '') {
                continue;
            }

            $storeId  = (int)$order->getStoreId();
            $amount   = (float)$order->getGrandTotal();
            $currency = (string)$order->getOrderCurrencyCode();

            try {
                $ok = $this->paythorAdapter->capturePayment($processToken, $amount, $currency, $storeId);

                if ($ok) {
                    $this->logger->info('Paythor CronCapture: capture acknowledged', [
                        'order_id' => $order->getIncrementId(),
                        'amount'   => $amount,
                        'currency' => $currency,
                    ]);
                    $count++;
                } else {
                    $this->logger->warning('Paythor CronCapture: capture returned error', [
                        'order_id' => $order->getIncrementId(),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Paythor CronCapture: exception during capture', [
                    'order_id' => $order->getIncrementId(),
                    'message'  => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            $this->logger->info("Paythor CronCapture: completed, {$count} order(s) captured.");
        }
    }
}
