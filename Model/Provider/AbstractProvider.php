<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\Provider;

use Digitalway\SocialLogin\Api\ProviderInterface;
use Digitalway\SocialLogin\Model\Config;
use Digitalway\SocialLogin\Model\Http\HttpClient;
use Digitalway\SocialLogin\Model\Http\HttpException;
use Digitalway\SocialLogin\Model\Profile;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractProvider implements ProviderInterface
{
    public function __construct(
        protected readonly Config $config,
        protected readonly HttpClient $http
    ) {
    }

    public function isEnabled(): bool
    {
        $code = $this->getCode();

        return $this->config->isModuleEnabled()
            && $this->config->isProviderEnabled($code)
            && $this->config->getClientId($code) !== ''
            && $this->config->getClientSecret($code) !== '';
    }

    public function fetchProfile(string $code): Profile
    {
        try {
            $raw = $this->requestProfile($code);
        } catch (HttpException $e) {
            throw new LocalizedException(
                __('Could not complete sign-in with %1. Please try again.', $this->getLabel()),
                $e
            );
        }

        return $this->normalizeProfile($raw);
    }

    /**
     * Exchange code -> token -> raw profile.
     *
     * @return array<string, mixed>
     * @throws HttpException
     */
    abstract protected function requestProfile(string $code): array;

    protected function clientId(): string
    {
        return $this->config->getClientId($this->getCode());
    }

    protected function clientSecret(): string
    {
        return $this->config->getClientSecret($this->getCode());
    }

    protected function redirectUri(): string
    {
        return $this->config->getRedirectUri($this->getCode());
    }

    /**
     * @param array<string, string> $params
     */
    protected function buildUrl(string $base, array $params): string
    {
        return $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $response
     * @throws HttpException
     */
    protected function requireAccessToken(array $response): string
    {
        $token = $response['access_token'] ?? '';
        if (!is_string($token) || $token === '') {
            throw new HttpException(sprintf('Access token missing from the %s response', $this->getLabel()));
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $raw
     * @throws LocalizedException
     */
    protected function requireId(array $raw, string $key): string
    {
        $id = $this->stringValue($raw, $key);
        if ($id === '') {
            throw new LocalizedException(__('Could not read your %1 profile. Please try again.', $this->getLabel()));
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $raw
     */
    protected function stringValue(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $raw
     */
    protected function emailValue(array $raw, string $key = 'email'): ?string
    {
        $email = $this->stringValue($raw, $key);

        return $email === '' ? null : $email;
    }

    protected function isTrue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1'
            || (is_string($value) && strtolower($value) === 'true');
    }

    /**
     * "Maria Grazia De Luca" -> ["Maria", "Grazia De Luca"]; "Cher" -> ["Cher", ""].
     *
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), 2) ?: [];

        return [(string) ($parts[0] ?? ''), (string) ($parts[1] ?? '')];
    }
}
