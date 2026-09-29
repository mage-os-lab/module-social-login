<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\Provider;

use Digitalway\SocialLogin\Model\Profile;

class Google extends AbstractProvider
{
    public const CODE = 'google';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'Google';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->buildUrl(self::AUTH_URL, [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);
    }

    protected function requestProfile(string $code): array
    {
        $token = $this->requireAccessToken($this->http->postForm(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]));

        return $this->http->get(self::USERINFO_URL, [], $token);
    }

    public function normalizeProfile(array $raw): Profile
    {
        $id = $this->requireId($raw, 'sub');
        $email = $this->emailValue($raw);
        [$first, $last] = $this->splitName($this->stringValue($raw, 'name'));
        $given = $this->stringValue($raw, 'given_name');
        $family = $this->stringValue($raw, 'family_name');

        return new Profile(
            self::CODE,
            $id,
            $email,
            $email !== null && $this->isTrue($raw['email_verified'] ?? false),
            $given !== '' ? $given : $first,
            $family !== '' ? $family : $last
        );
    }
}
