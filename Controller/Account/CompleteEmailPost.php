<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Controller\Account;

use Digitalway\SocialLogin\Model\CustomerCreator;
use Digitalway\SocialLogin\Model\CustomerLinker;
use Digitalway\SocialLogin\Model\LoginHandler;
use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\ProfileLoginFlow;
use Digitalway\SocialLogin\Model\ProviderPool;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * POST of the completion form (form_key checked by the core CsrfValidator).
 * Email of an existing account: NO linking here, only after the password
 * sign-in (Observer\LinkPendingIdentity).
 */
class CompleteEmailPost implements HttpPostActionInterface
{
    private const FORM_ROUTE = 'sociallogin/account/completeemail';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly PendingProfileStorage $pendingStorage,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerCreator $creator,
        private readonly CustomerLinker $linker,
        private readonly AccountManagementInterface $accountManagement,
        private readonly LoginHandler $loginHandler,
        private readonly ProfileLoginFlow $flow,
        private readonly ProviderPool $providerPool,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();

        $pending = $this->pendingStorage->get();
        if ($pending === null) {
            $this->messageManager->addErrorMessage(__('Your sign-in session has expired. Please try again.'));

            return $redirect->setPath('customer/account/login');
        }

        $provider = $pending->profile->provider;
        $label = $this->providerPool->get($provider)?->getLabel() ?? ucfirst($provider);

        $email = trim((string) $this->request->getParam('email'));
        $firstname = trim((string) $this->request->getParam('firstname'));
        $lastname = trim((string) $this->request->getParam('lastname'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || $firstname === '' || $lastname === ''
            || mb_strlen($firstname) > 255 || mb_strlen($lastname) > 255
        ) {
            $this->messageManager->addErrorMessage(__('Please enter a valid email address, first name and last name.'));

            return $redirect->setPath(self::FORM_ROUTE);
        }

        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();
        try {
            $this->customerRepository->get($email, $websiteId);
            $this->pendingStorage->setClaimedEmail($email);
            $this->messageManager->addNoticeMessage(__(
                'An account with this email already exists: sign in with your password to link %1.',
                $label
            ));

            return $redirect->setPath('customer/account/login');
        } catch (NoSuchEntityException $e) {
            unset($e); // Customer does not exist; email is available for registration
        } catch (\Throwable $e) {
            return $this->unexpectedError($e, $provider, $label, $redirect);
        }

        $profile = $pending->profile->withContactData($email, $firstname, $lastname);

        try {
            $customer = $this->creator->createUnverified($profile);
            $this->linker->link((int) $customer->getId(), $profile);
            $this->pendingStorage->clear();

            $status = $this->accountManagement->getConfirmationStatus((int) $customer->getId());
            if ($status === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $this->messageManager->addSuccessMessage(
                    __('Account created. Check your email to confirm it, then sign in.')
                );

                return $redirect->setPath('customer/account/login');
            }

            $this->loginHandler->login($customer);

            return $redirect->setUrl($this->flow->redirectUrl($pending->returnUrl));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->warning('SocialLogin: email completion failed', [
                'provider' => $provider,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return $redirect->setPath(self::FORM_ROUTE);
        } catch (\Throwable $e) {
            return $this->unexpectedError($e, $provider, $label, $redirect);
        }
    }

    /**
     * Database or other unexpected errors: generic message instead of an error page.
     * If the account was already created, retrying the form asks for the password
     * sign-in, which then links the identity.
     *
     * @param \Throwable $e
     * @param string $provider
     * @param string $label
     * @param Redirect $redirect
     * @return Redirect
     */
    private function unexpectedError(\Throwable $e, string $provider, string $label, Redirect $redirect): Redirect
    {
        $this->messageManager->addErrorMessage(__('Could not complete sign-in with %1. Please try again.', $label));
        $this->logger->error('SocialLogin: unexpected error during email completion', [
            'provider' => $provider,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        return $redirect->setPath(self::FORM_ROUTE);
    }
}
