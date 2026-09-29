<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CustomerCreator
{
    public function __construct(
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountManagementInterface $accountManagement,
        private readonly EmailNotificationInterface $emailNotification,
        private readonly StoreManagerInterface $storeManager,
        private readonly EncryptorInterface $encryptor,
        private readonly Random $random,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Email verified by the provider: no confirmation. Random password (the
     * customer can set one via "Forgot password"); "no password" welcome
     * email.
     *
     * @throws LocalizedException
     */
    public function createVerified(Profile $profile): CustomerInterface
    {
        $passwordHash = $this->encryptor->getHash($this->random->getRandomString(32), true);
        $saved = $this->customerRepository->save($this->newCustomer($profile), $passwordHash);

        try {
            $this->emailNotification->newAccount(
                $saved,
                EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED_NO_PASSWORD,
                '',
                (int) $saved->getStoreId()
            );
        } catch (\Throwable $e) {
            $this->logger->warning('SocialLogin: welcome email not sent', [
                'customer_id' => $saved->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $saved;
    }

    /**
     * Email entered by the user: standard Magento flow (honours the
     * "require email confirmation" setting and sends the matching email).
     *
     * @throws LocalizedException
     */
    public function createUnverified(Profile $profile): CustomerInterface
    {
        return $this->accountManagement->createAccount($this->newCustomer($profile));
    }

    private function newCustomer(Profile $profile): CustomerInterface
    {
        $store = $this->storeManager->getStore();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId((int) $store->getWebsiteId());
        $customer->setStoreId((int) $store->getId());
        $customer->setEmail((string) $profile->email);
        $customer->setFirstname($profile->firstname);
        $customer->setLastname($profile->lastname);

        return $customer;
    }
}
