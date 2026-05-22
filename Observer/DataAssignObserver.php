<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    private const ALLOWED_KEYS = [
        'paythor_token',
        'payment_method',
    ];

    /**
     * Execute the action.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach (self::ALLOWED_KEYS as $key) {
            if (isset($additionalData[$key])) {
                $paymentInfo->setAdditionalInformation($key, $additionalData[$key]);
            }
        }
    }
}
