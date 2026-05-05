<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Paythor\SanalPosPro\Model\Config\PaymentConfig;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'paythor_sanalpospro';

    public function __construct(
        private readonly PaymentConfig $paymentConfig
    ) {
    }

    public function getConfig(): array
    {
        if (!$this->paymentConfig->isOperational()) {
            return [];
        }

        return [
            'payment' => [
                self::CODE => [
                    'isActive'  => $this->paymentConfig->isActive(),
                    'title'     => $this->paymentConfig->getTitle(),
                    'isSandbox' => $this->paymentConfig->isSandboxMode(),
                ],
            ],
        ];
    }
}
