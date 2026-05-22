<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Sanalpospro\SanalPosPro\Model\Config\PaymentConfig;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Sanalpospro_SanalPosPro::connect';

    /**
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param PaymentConfig $paymentConfig
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly PaymentConfig $paymentConfig
    ) {
        parent::__construct($context);
    }

    /**
     * Execute the action.
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Paythor – Connect Your Account'));

        return $page;
    }
}
