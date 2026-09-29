<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Api;

use Digitalway\SocialLogin\Model\Profile;
use Magento\Framework\Exception\LocalizedException;

interface ProviderInterface
{
    /** Stable code used in URLs, config and the identity table (google, facebook, ...). */
    public function getCode(): string;

    public function getLabel(): string;

    /** Module enabled + provider enabled + client ID and secret set. */
    public function isEnabled(): bool;

    public function getAuthorizationUrl(string $state): string;

    /**
     * Exchanges the OAuth code for the normalized user profile.
     *
     * @throws LocalizedException
     */
    public function fetchProfile(string $code): Profile;

    /**
     * Converts the raw provider response into a Profile (pure, no network).
     *
     * @param array<string, mixed> $raw
     * @throws LocalizedException
     */
    public function normalizeProfile(array $raw): Profile;
}
