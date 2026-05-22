<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Block\Adminhtml\Connect;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;
use Sanalpospro\SanalPosPro\Model\Config\PaymentConfig;

class ConnectBlock extends Template
{
    /**
     *
     * @param Context $context
     * @param PaymentConfig $paymentConfig
     * @param StatusCollectionFactory $statusCollectionFactory
     * @param ResourceConnection $resource
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PaymentConfig $paymentConfig,
        private readonly StatusCollectionFactory $statusCollectionFactory,
        private readonly ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get iapi url.
     *
     * @return string
     */
    public function getIapiUrl(): string
    {
        $base = rtrim($this->_storeManager->getStore()->getBaseUrl(), '/');
        return $base . '/paythor/iapi/index';
    }

    /**
     * Get xfvv.
     *
     * @return string
     */
    public function getXfvv(): string
    {
        return $this->paymentConfig->getXfvv();
    }

    /**
     * Get store base url.
     *
     * @return string
     */
    public function getStoreBaseUrl(): string
    {
        return rtrim((string)$this->_storeManager->getStore()->getBaseUrl(), '/');
    }

    /**
     * Get order status options.
     *
     * @return array
     */
    public function getOrderStatusOptions(): array
    {
        $options = [];
        foreach ($this->statusCollectionFactory->create()->load() as $status) {
            $options[(string)$status->getStatus()] = (string)$status->getLabel();
        }
        return $options;
    }

    /**
     * Get payment config.
     *
     * @return PaymentConfig
     */
    public function getPaymentConfig(): PaymentConfig
    {
        return $this->paymentConfig;
    }

    /**
     * Read a setting saved by the React UI via IAPI (lowercase config key,.
     *
     * E.g. `showinstallmentstabs`, `currency_convert`, `paymentpagetheme`).
     * Bypasses the config cache so the value reflects the latest save —
     * fixes the toggle flipping back to the default after a page reload.
     *
     * @param string $key
     * @param string $default
     */
    public function getModuleSetting(string $key, string $default = ''): string
    {
        $connection = $this->resource->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('core_config_data'), 'value')
                ->where('path = ?', 'payment/paythor_sanalpospro/' . strtolower($key))
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0)
        );
        $value = trim((string)($value ?? ''));
        return $value !== '' ? $value : $default;
    }
}
