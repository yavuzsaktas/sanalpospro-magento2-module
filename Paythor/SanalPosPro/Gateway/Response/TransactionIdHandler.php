<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class TransactionIdHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();

        $transactionId = $response['transaction_id'] ?? $response['paythor_transaction_id'] ?? null;
        if ($transactionId !== null) {
            $payment->setTransactionId((string) $transactionId);
            $payment->setAdditionalInformation('paythor_transaction_id', (string) $transactionId);
        }

        $payment->setIsTransactionClosed(false);
    }
}
