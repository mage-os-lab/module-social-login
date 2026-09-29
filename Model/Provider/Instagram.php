<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\Provider;

use Digitalway\SocialLogin\Model\Profile;

/**
 * "Instagram API with Instagram Login": Business/Creator accounts only,
 * no email available (the customer enters it in the completion form).
 */
class Instagram extends AbstractProvider
{
    public const CODE = 'instagram';

    private const AUTH_URL = 'https://www.instagram.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const ME_URL = 'https://graph.instagram.com/%s/me';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'Instagram';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->buildUrl(self::AUTH_URL, [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'instagram_business_basic',
            'state' => $state,
        ]);
    }

    protected function requestProfile(string $code): array
    {
        $response = $this->http->postForm(self::TOKEN_URL, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);
        // The endpoint returns either { access_token, ... } or { data: [ { access_token, ... } ] }.
        if (isset($response['data'][0]) && is_array($response['data'][0])) {
            $response = $response['data'][0];
        }
        $token = $this->requireAccessToken($response);

        return $this->http->get(sprintf(self::ME_URL, $this->config->getGraphVersion(self::CODE)), [
            'fields' => 'id,username,name',
            'access_token' => $token,
        ]);
    }

    public function normalizeProfile(array $raw): Profile
    {
        $id = $this->requireId($raw, 'id');
        [$first, $last] = $this->splitName($this->stringValue($raw, 'name'));
        if ($first === '') {
            $first = $this->stringValue($raw, 'username');
        }

        return new Profile(self::CODE, $id, null, false, $first, $last);
    }
}
