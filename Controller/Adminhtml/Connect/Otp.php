<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\View\Result\PageFactory;

/**
 * Step 2: shows the OTP input form.
 */
class Otp extends Action
{
    public const ADMIN_RESOURCE = 'Sanalpospro_SanalPosPro::connect';

    /**
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Session $backendSession
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Session $backendSession
    ) {
        parent::__construct($context);
    }

    /**
     * Execute the action.
     */
    public function execute()
    {
        // Guard: if no temp token in session, redirect back to login
        if (!$this->backendSession->getPaythorTempToken()) {
            $this->messageManager->addErrorMessage(__('Session expired. Please log in again.'));
            return $this->resultRedirectFactory->create()
                ->setPath('paythor_sanalpospro/connect/index');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Paythor – Enter OTP Code'));
        return $page;
    }
}
