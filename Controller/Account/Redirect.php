<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Controller\Account;

use Digitalway\SocialLogin\Model\ProviderPool;
use Digitalway\SocialLogin\Model\ReturnUrlValidator;
use Digitalway\SocialLogin\Model\StateManager;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * GET /sociallogin/account/redirect/provider/{code}/[referer/{base64}/]
 */
class Redirect implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ProviderPool $providerPool,
        private readonly StateManager $stateManager,
        private readonly ReturnUrlValidator $returnUrlValidator,
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();

        if ($this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account');
        }

        $provider = $this->providerPool->get((string) $this->request->getParam('provider'));
        if ($provider === null || !$provider->isEnabled()) {
            $this->messageManager->addErrorMessage(__('This sign-in method is not available.'));

            return $redirect->setPath('customer/account/login');
        }

        $returnUrl = $this->returnUrlValidator->fromEncodedReferer((string) $this->request->getParam('referer'));
        $state = $this->stateManager->create($provider->getCode(), $returnUrl);

        return $redirect->setUrl($provider->getAuthorizationUrl($state));
    }
}
