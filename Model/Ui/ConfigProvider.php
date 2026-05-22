<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Sanalpospro\SanalPosPro\Model\Config\PaymentConfig;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'paythor_sanalpospro';

    /**
     *
     * @param PaymentConfig $paymentConfig
     */
    public function __construct(
        private readonly PaymentConfig $paymentConfig
    ) {
    }

    /**
     * Get config.
     *
     * @return array
     */
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
