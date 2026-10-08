<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\ResourceModel;

use Magento\Customer\Model\Config\Share as CustomerShareConfig;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Identity extends AbstractDb
{
    public const TABLE = 'digitalway_social_identity';

    public function __construct(
        Context $context,
        private readonly CustomerShareConfig $shareConfig,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'identity_id');
    }

    public function findCustomerId(string $provider, string $providerUserId, int $websiteId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['customer_id'])
            ->where('provider = ?', $provider)
            ->where('provider_user_id = ?', $providerUserId);

        if ($this->shareConfig->isWebsiteScope()) {
            $select->where('website_id = ?', $websiteId);
        }

        $select->limit(1);
        $customerId = $connection->fetchOne($select);

        return $customerId === false || $customerId === null ? null : (int) $customerId;
    }

    public function hasProvider(int $customerId, string $provider, ?int $websiteId = null): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['identity_id'])
            ->where('customer_id = ?', $customerId)
            ->where('provider = ?', $provider);

        if ($websiteId !== null && $this->shareConfig->isWebsiteScope()) {
            $select->where('website_id = ?', $websiteId);
        }

        $select->limit(1);

        return $connection->fetchOne($select) !== false;
    }

    /**
     * The hasProvider() check and this insert are not atomic: a double click or two
     * parallel callbacks hit the UNIQUE constraints. DuplicateException is not a
     * LocalizedException, so it is converted here, where its meaning is known.
     *
     * @throws LocalizedException
     */
    public function link(int $customerId, int $websiteId, string $provider, string $providerUserId, ?string $email): void
    {
        $existingCustomerId = $this->findCustomerId($provider, $providerUserId, $websiteId);
        if ($existingCustomerId !== null && $existingCustomerId !== $customerId) {
            throw new LocalizedException(
                __('This profile is already linked to an account. Please try signing in again.')
            );
        }

        try {
            $this->getConnection()->insert($this->getMainTable(), [
                'customer_id' => $customerId,
                'website_id' => $websiteId,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email' => $email,
            ]);
        } catch (DuplicateException $e) {
            throw new LocalizedException(
                __('This profile is already linked to an account. Please try signing in again.'),
                $e
            );
        }
    }
}
