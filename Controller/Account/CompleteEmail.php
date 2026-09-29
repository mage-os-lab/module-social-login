<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Controller\Account;

use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;

class CompleteEmail implements HttpGetActionInterface
{
    public function __construct(
        private readonly PendingProfileStorage $pendingStorage,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->pendingStorage->get() === null) {
            $this->messageManager->addErrorMessage(__('Your sign-in session has expired. Please try again.'));

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Complete sign-in'));

        return $page;
    }
}
