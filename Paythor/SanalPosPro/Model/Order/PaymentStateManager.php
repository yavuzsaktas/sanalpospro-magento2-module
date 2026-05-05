<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Model\Order;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;

class PaymentStateManager
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderManagementInterface $orderManagement,
        private readonly InvoiceManagementInterface $invoiceManagement,
        private readonly TransactionFactory $transactionFactory,
        private readonly OrderSender $orderSender,
        private readonly InvoiceSender $invoiceSender,
        private readonly LoggerInterface $logger
    ) {
    }

    public function markPaid(Order $order, string $transactionId): void
    {
        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            $this->logger->info('Paythor PaymentStateManager: order already finalized, markPaid skipped', [
                'order' => $order->getIncrementId(),
                'state' => $order->getState(),
            ]);
            return;
        }

        $payment = $order->getPayment();
        if ($transactionId !== '') {
            $payment->setTransactionId($transactionId)
                    ->setLastTransId($transactionId)
                    ->setAdditionalInformation('paythor_transaction_id', $transactionId);
        }

        if ($order->canInvoice()) {
            $invoice = $this->invoiceManagement->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->setTransactionId($transactionId);

            $this->transactionFactory->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            try {
                $this->invoiceSender->send($invoice);
            } catch (\Throwable $e) {
                $this->logger->warning('Paythor: invoice email send failed (non-fatal)', [
                    'order'   => $order->getIncrementId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $order->setState(Order::STATE_PROCESSING)
              ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
              ->addCommentToStatusHistory(
                  __('Paythor: payment confirmed. Transaction ID: %1', $transactionId ?: 'n/a'),
                  false,
                  true
              );

        $this->orderRepository->save($order);

        try {
            $this->orderSender->send($order);
        } catch (\Throwable $e) {
            $this->logger->warning('Paythor: order email send failed (non-fatal)', [
                'order'   => $order->getIncrementId(),
                'message' => $e->getMessage(),
            ]);
        }

        $this->logger->info('Paythor PaymentStateManager: order moved to PROCESSING', [
            'order'          => $order->getIncrementId(),
            'transaction_id' => $transactionId,
        ]);
    }

    public function markFailed(Order $order, string $reason): void
    {
        if ($order->canCancel()) {
            $this->orderManagement->cancel($order->getEntityId());
            $order = $this->orderRepository->get($order->getEntityId());
        }

        $order->addCommentToStatusHistory(__('Paythor: payment failed — %1', $reason));
        $this->orderRepository->save($order);

        $this->logger->info('Paythor PaymentStateManager: order cancelled due to failed payment', [
            'order'  => $order->getIncrementId(),
            'reason' => $reason,
        ]);
    }

    public function markRefunded(Order $order, string $transactionId, string $reason = ''): void
    {
        if (in_array($order->getState(), [Order::STATE_CLOSED], true)) {
            $this->logger->info('Paythor PaymentStateManager: order already closed, markRefunded skipped', [
                'order' => $order->getIncrementId(),
            ]);
            return;
        }

        $comment = $transactionId !== ''
            ? __('Paythor: payment refunded (E İade Edildi). Transaction ID: %1', $transactionId)
            : __('Paythor: payment refunded (E İade Edildi).');

        if ($reason !== '') {
            $comment = $comment . ' ' . $reason;
        }

        $order->addCommentToStatusHistory($comment, false, true);

        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            $order->setState(Order::STATE_CLOSED)
                  ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CLOSED));
        }

        $this->orderRepository->save($order);

        $this->logger->info('Paythor PaymentStateManager: order marked as refunded', [
            'order'          => $order->getIncrementId(),
            'transaction_id' => $transactionId,
        ]);
    }

    public function markPending(Order $order, string $paythorStatus): void
    {
        $order->addCommentToStatusHistory(
            __('Paythor: payment status updated — %1', $paythorStatus)
        );
        $this->orderRepository->save($order);

        $this->logger->info('Paythor PaymentStateManager: pending status recorded', [
            'order'  => $order->getIncrementId(),
            'status' => $paythorStatus,
        ]);
    }
}
