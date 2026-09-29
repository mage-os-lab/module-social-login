<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;

/**
 * OAuth "state" parameter (anti-CSRF): bound to the provider, single use.
 */
class StateManager
{
    private const KEY = 'oauth_state';

    public function __construct(
        private readonly SessionBag $bag,
        private readonly Random $random
    ) {
    }

    public function create(string $provider, string $returnUrl): string
    {
        $state = $this->random->getUniqueHash();
        $this->bag->set(self::KEY, ['state' => $state, 'provider' => $provider, 'return_url' => $returnUrl]);

        return $state;
    }

    /**
     * Validates the received state and returns the stored return URL. The stored
     * state is removed in any case: it is valid only once.
     *
     * @throws LocalizedException
     */
    public function consume(string $provider, string $state): string
    {
        $data = $this->bag->pull(self::KEY);

        $valid = is_array($data)
            && $state !== ''
            && ($data['provider'] ?? null) === $provider
            && hash_equals((string) ($data['state'] ?? ''), $state);

        if (!$valid) {
            throw new LocalizedException(__('Invalid or expired request. Please try signing in again.'));
        }

        return (string) ($data['return_url'] ?? '');
    }

    public function discard(): void
    {
        $this->bag->remove(self::KEY);
    }
}
