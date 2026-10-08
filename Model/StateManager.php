<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * OAuth "state" parameter (anti-CSRF): bound to the provider, single use.
 * Preserves a bounded map of states to support concurrent provider flows in the same session.
 */
class StateManager
{
    public const KEY = 'oauth_state';
    public const MAX_STATES = 10;
    public const TTL_SECONDS = 1800;

    public function __construct(
        private readonly SessionBag $bag,
        private readonly Random $random,
        private readonly ?DateTime $dateTime = null
    ) {
    }

    public function create(string $provider, string $returnUrl): string
    {
        $state = $this->random->getUniqueHash();

        $states = $this->getStates();
        $states = $this->pruneExpired($states);

        while (count($states) >= self::MAX_STATES) {
            array_shift($states);
        }

        $states[$state] = [
            'provider' => $provider,
            'return_url' => $returnUrl,
            'created_at' => $this->now(),
        ];

        $this->saveStates($states);

        return $state;
    }

    /**
     * Validates the received state and returns the stored return URL.
     * The matched state is removed: it is valid only once.
     * Any unmatched/concurrent states remain valid.
     *
     * @throws LocalizedException
     */
    public function consume(string $provider, string $state): string
    {
        if ($state === '') {
            throw new LocalizedException(__('Invalid or expired request. Please try signing in again.'));
        }

        $states = $this->getStates();

        $matchedKey = null;
        $matchedEntry = null;

        foreach ($states as $storedState => $entry) {
            if (is_array($entry) && hash_equals((string) $storedState, $state)) {
                $matchedKey = (string) $storedState;
                $matchedEntry = $entry;
                break;
            }
        }

        if ($matchedKey === null || $matchedEntry === null) {
            throw new LocalizedException(__('Invalid or expired request. Please try signing in again.'));
        }

        // Single-use: remove matched state immediately so it cannot be reused
        unset($states[$matchedKey]);
        $this->saveStates($states);

        $valid = ($matchedEntry['provider'] ?? null) === $provider
            && ($this->now() - (int) ($matchedEntry['created_at'] ?? 0) <= self::TTL_SECONDS);

        if (!$valid) {
            throw new LocalizedException(__('Invalid or expired request. Please try signing in again.'));
        }

        return (string) ($matchedEntry['return_url'] ?? '');
    }

    public function discard(?string $state = null, ?string $provider = null): void
    {
        if ($state === null && $provider === null) {
            $this->bag->remove(self::KEY);
            return;
        }

        $states = $this->getStates();

        if ($state !== null && $state !== '') {
            foreach (array_keys($states) as $storedState) {
                if (hash_equals((string) $storedState, $state)) {
                    unset($states[$storedState]);
                }
            }
        } elseif ($provider !== null && $provider !== '') {
            foreach ($states as $storedState => $entry) {
                if (($entry['provider'] ?? null) === $provider) {
                    unset($states[$storedState]);
                }
            }
        }

        $this->saveStates($states);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStates(): array
    {
        $data = $this->bag->get(self::KEY);
        if (!is_array($data)) {
            return [];
        }

        // Backward compatibility: old format was a single associative array with 'state' key
        if (isset($data['state']) && is_string($data['state'])) {
            return [
                $data['state'] => [
                    'provider' => (string) ($data['provider'] ?? ''),
                    'return_url' => (string) ($data['return_url'] ?? ''),
                    'created_at' => $this->now(),
                ],
            ];
        }

        $result = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && is_array($v)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private function saveStates(array $states): void
    {
        if (empty($states)) {
            $this->bag->remove(self::KEY);
        } else {
            $this->bag->set(self::KEY, $states);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $states
     * @return array<string, array<string, mixed>>
     */
    private function pruneExpired(array $states): array
    {
        $now = $this->now();
        foreach ($states as $storedState => $entry) {
            $createdAt = (int) ($entry['created_at'] ?? 0);
            if ($createdAt > 0 && ($now - $createdAt) > self::TTL_SECONDS) {
                unset($states[$storedState]);
            }
        }

        return $states;
    }

    private function now(): int
    {
        return $this->dateTime !== null ? $this->dateTime->gmtTimestamp() : time();
    }
}
