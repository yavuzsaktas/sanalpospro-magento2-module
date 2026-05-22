<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundDataBuilder implements BuilderInterface
{
    /**
     * Build.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment   = $paymentDO->getPayment();

        $transactionId = $payment->getParentTransactionId()
            ?: $payment->getLastTransId()
            ?: $payment->getAdditionalInformation('paythor_transaction_id');

        return [
            '__action'       => 'refund',
            'transaction_id' => $transactionId,
            'amount'         => (float) SubjectReader::readAmount($buildSubject),
            'currency'       => $paymentDO->getOrder()->getCurrencyCode(),
            'store_id'       => (int) $paymentDO->getOrder()->getStoreId(),
        ];
    }
}
