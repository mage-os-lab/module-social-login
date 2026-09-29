<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Social profile awaiting customer input (email/name), kept in session with an expiry.
 */
class PendingProfileStorage
{
    public const TTL_SECONDS = 1800;

    private const KEY = 'pending_profile';

    public function __construct(
        private readonly SessionBag $bag,
        private readonly DateTime $dateTime
    ) {
    }

    public function save(Profile $profile, string $returnUrl): void
    {
        $this->bag->set(self::KEY, [
            'profile' => $profile->toArray(),
            'return_url' => $returnUrl,
            'claimed_email' => null,
            'created_at' => $this->now(),
        ]);
    }

    public function get(): ?PendingLogin
    {
        $data = $this->bag->get(self::KEY);
        if (!is_array($data) || !is_array($data['profile'] ?? null) || !isset($data['created_at'])) {
            return null;
        }

        if ($this->now() - (int) $data['created_at'] > self::TTL_SECONDS) {
            $this->clear();

            return null;
        }

        try {
            $profile = Profile::fromArray($data['profile']);
        } catch (\InvalidArgumentException) {
            $this->clear();

            return null;
        }

        $claimed = $data['claimed_email'] ?? null;

        return new PendingLogin(
            $profile,
            (string) ($data['return_url'] ?? ''),
            is_string($claimed) && $claimed !== '' ? $claimed : null
        );
    }

    public function setClaimedEmail(string $email): void
    {
        $data = $this->bag->get(self::KEY);
        if (!is_array($data)) {
            return;
        }

        $data['claimed_email'] = $email;
        $this->bag->set(self::KEY, $data);
    }

    public function clear(): void
    {
        $this->bag->remove(self::KEY);
    }

    private function now(): int
    {
        return (int) $this->dateTime->gmtTimestamp();
    }
}
