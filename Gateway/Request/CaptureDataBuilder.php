<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CaptureDataBuilder implements BuilderInterface
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

        $transactionId = $payment->getLastTransId()
            ?: $payment->getAdditionalInformation('paythor_transaction_id')
            ?: $payment->getAdditionalInformation('paythor_process_token');

        return [
            '__action'       => 'capture',
            'transaction_id' => $transactionId,
            'amount'         => (float) SubjectReader::readAmount($buildSubject),
            'currency'       => $paymentDO->getOrder()->getCurrencyCode(),
            'store_id'       => (int) $paymentDO->getOrder()->getStoreId(),
        ];
    }
}
