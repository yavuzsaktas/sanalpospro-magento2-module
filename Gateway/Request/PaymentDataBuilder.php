<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentDataBuilder implements BuilderInterface
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
        $order     = $paymentDO->getOrder();
        $payment   = $paymentDO->getPayment();

        return [
            'order_id'     => (int) $order->getId(),
            'increment_id' => $order->getOrderIncrementId(),
            'amount'       => (float) SubjectReader::readAmount($buildSubject),
            'currency'     => $order->getCurrencyCode(),
            'store_id'     => (int) $order->getStoreId(),
        ];
    }
}
