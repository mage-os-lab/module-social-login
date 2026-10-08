<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Digitalway\SocialLogin\Model\ResourceModel\Identity;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class CustomerLinker
{
    public function __construct(
        private readonly Identity $identity,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerCreator $creator,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * 1. known identity -> that customer
     * 2. verified email + existing customer -> link
     * 3. verified email + no customer + first and last name -> create and link
     * otherwise -> pending (completion form). Never link on an unverified email.
     *
     * @throws LocalizedException
     */
    public function resolve(Profile $profile): LinkResult
    {
        $websiteId = $this->websiteId();

        $customerId = $this->identity->findCustomerId($profile->provider, $profile->providerUserId, $websiteId);
        if ($customerId !== null) {
            return LinkResult::loggedIn($this->customerRepository->getById($customerId));
        }

        if (!$profile->hasVerifiedEmail()) {
            return LinkResult::pending();
        }

        try {
            $customer = $this->customerRepository->get((string) $profile->email, $websiteId);
        } catch (NoSuchEntityException) {
            if (!$profile->hasNames()) {
                return LinkResult::pending();
            }
            $customer = $this->creator->createVerified($profile);
        }

        $this->link((int) $customer->getId(), $profile);

        return LinkResult::loggedIn($customer);
    }

    /**
     * @throws LocalizedException
     */
    public function link(int $customerId, Profile $profile): void
    {
        if ($this->identity->hasProvider($customerId, $profile->provider, $this->websiteId())) {
            throw new LocalizedException(__(
                'Your account is already linked to another profile on this network. Sign in with that profile or with your email and password.'
            ));
        }

        $this->identity->link(
            $customerId,
            $this->websiteId(),
            $profile->provider,
            $profile->providerUserId,
            $profile->email
        );
    }

    private function websiteId(): int
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }
}
