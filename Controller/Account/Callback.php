<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Controller\Account;

use Digitalway\SocialLogin\Model\ProfileLoginFlow;
use Digitalway\SocialLogin\Model\ProviderPool;
use Digitalway\SocialLogin\Model\StateManager;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /sociallogin/account/callback/provider/{code}/ (Redirect URI registered with the providers).
 * Codes, tokens and secrets never end up in the logs.
 */
class Callback implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ProviderPool $providerPool,
        private readonly StateManager $stateManager,
        private readonly ProfileLoginFlow $flow,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();
        $code = (string) $this->request->getParam('provider');
        $provider = $this->providerPool->get($code);

        if ($provider === null || !$provider->isEnabled()) {
            $this->messageManager->addErrorMessage(__('This sign-in method is not available.'));

            return $redirect->setPath('customer/account/login');
        }

        $label = $provider->getLabel();

        $error = (string) $this->request->getParam('error');
        if ($error !== '') {
            $this->stateManager->discard((string) $this->request->getParam('state'), $code);
            $this->logger->info('SocialLogin: sign-in cancelled at the provider', [
                'provider' => $code,
                'error' => substr((string) preg_replace('/[^A-Za-z0-9_.-]/', '', $error), 0, 64),
            ]);
            $this->messageManager->addNoticeMessage(__('Sign-in with %1 was cancelled.', $label));

            return $redirect->setPath('customer/account/login');
        }

        try {
            $returnUrl = $this->stateManager->consume($code, (string) $this->request->getParam('state'));

            $authCode = (string) $this->request->getParam('code');
            if ($authCode === '') {
                throw new LocalizedException(__('Invalid response from %1. Please try signing in again.', $label));
            }

            $profile = $provider->fetchProfile($authCode);

            return $redirect->setUrl($this->flow->complete($profile, $returnUrl));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->warning('SocialLogin: sign-in failed', [
                'provider' => $code,
                'error' => $e->getMessage(),
                'cause' => $e->getPrevious()?->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not complete sign-in with %1. Please try again.', $label));
            $this->logger->error('SocialLogin: unexpected error', [
                'provider' => $code,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        return $redirect->setPath('customer/account/login');
    }
}
