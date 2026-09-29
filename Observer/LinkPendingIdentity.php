<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Observer;

use Digitalway\SocialLogin\Model\CustomerLinker;
use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\ProviderPool;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * customer_data_object_login: if the customer entered the email of an existing
 * account in the completion form, the social identity is linked only now, after
 * they have proved they own the account (password sign-in).
 */
class LinkPendingIdentity implements ObserverInterface
{
    public function __construct(
        private readonly PendingProfileStorage $pendingStorage,
        private readonly CustomerLinker $linker,
        private readonly ProviderPool $providerPool,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $pending = $this->pendingStorage->get();
        if ($pending === null || $pending->claimedEmail === null) {
            return;
        }

        $customer = $observer->getEvent()->getData('customer');
        if (!$customer instanceof CustomerInterface
            || strcasecmp((string) $customer->getEmail(), $pending->claimedEmail) !== 0
        ) {
            return;
        }

        $provider = $pending->profile->provider;
        $label = $this->providerPool->get($provider)?->getLabel() ?? ucfirst($provider);
        $profile = $pending->profile->withContactData(
            $pending->claimedEmail,
            (string) $customer->getFirstname(),
            (string) $customer->getLastname()
        );

        try {
            $this->linker->link((int) $customer->getId(), $profile);
            $this->messageManager->addSuccessMessage(__('Your %1 profile has been linked to your account.', $label));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('SocialLogin: linking of the pending identity failed', [
                'provider' => $provider,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->pendingStorage->clear();
        }
    }
}
