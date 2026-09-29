<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\Provider;

use Digitalway\SocialLogin\Model\Profile;

/**
 * "Sign In with LinkedIn using OpenID Connect".
 */
class LinkedIn extends AbstractProvider
{
    public const CODE = 'linkedin';

    private const AUTH_URL = 'https://www.linkedin.com/oauth/v2/authorization';
    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';
    private const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'LinkedIn';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->buildUrl(self::AUTH_URL, [
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid profile email',
            'state' => $state,
        ]);
    }

    protected function requestProfile(string $code): array
    {
        $token = $this->requireAccessToken($this->http->postForm(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
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
