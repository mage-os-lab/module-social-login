<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\Provider;

use Digitalway\SocialLogin\Model\Profile;

class Facebook extends AbstractProvider
{
    public const CODE = 'facebook';

    private const AUTH_URL = 'https://www.facebook.com/%s/dialog/oauth';
    private const TOKEN_URL = 'https://graph.facebook.com/%s/oauth/access_token';
    private const ME_URL = 'https://graph.facebook.com/%s/me';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'Facebook';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->buildUrl(sprintf(self::AUTH_URL, $this->version()), [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'email,public_profile',
            'state' => $state,
        ]);
    }

    protected function requestProfile(string $code): array
    {
        $token = $this->requireAccessToken($this->http->get(sprintf(self::TOKEN_URL, $this->version()), [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
        ]));

        return $this->http->get(sprintf(self::ME_URL, $this->version()), [
            'fields' => 'id,email,first_name,last_name',
            'appsecret_proof' => hash_hmac('sha256', $token, $this->clientSecret()),
        ], $token);
    }

    /**
     * Meta returns the email only if confirmed on the account: if present, it is verified.
     */
    public function normalizeProfile(array $raw): Profile
    {
        $id = $this->requireId($raw, 'id');
        $email = $this->emailValue($raw);
        [$first, $last] = $this->splitName($this->stringValue($raw, 'name'));
        $firstName = $this->stringValue($raw, 'first_name');
        $lastName = $this->stringValue($raw, 'last_name');

        return new Profile(
            self::CODE,
            $id,
            $email,
            $email !== null,
            $firstName !== '' ? $firstName : $first,
            $lastName !== '' ? $lastName : $last
        );
    }

    private function version(): string
    {
        return $this->config->getGraphVersion(self::CODE);
    }
}
