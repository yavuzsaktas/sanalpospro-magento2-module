<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AuthorizationDataBuilder implements BuilderInterface
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

        return [
            '__action'        => 'authorize',
            'paythor_token'   => $payment->getAdditionalInformation('paythor_token') ?? '',
            'payment_method'  => $payment->getAdditionalInformation('payment_method') ?? 'credit_card',
        ];
    }
}
