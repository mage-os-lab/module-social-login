<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model\Provider;

use Digitalway\SocialLogin\Model\Config;
use Digitalway\SocialLogin\Model\Http\HttpClient;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\Provider\Facebook;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FacebookTest extends TestCase
{
    private const REDIRECT = 'https://shop.test/sociallogin/account/callback/provider/facebook/';

    private HttpClient&MockObject $http;
    private Facebook $provider;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getClientId')->willReturn('appid');
        $config->method('getClientSecret')->willReturn('appsecret');
        $config->method('getRedirectUri')->willReturn(self::REDIRECT);
        $config->method('getGraphVersion')->willReturn('v24.0');
        $this->http = $this->createMock(HttpClient::class);
        $this->provider = new Facebook($config, $this->http);
    }

    public function testCodeAndLabel(): void
    {
        self::assertSame('facebook', $this->provider->getCode());
        self::assertSame('Facebook', $this->provider->getLabel());
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl('st4te');

        self::assertStringStartsWith('https://www.facebook.com/v24.0/dialog/oauth?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('appid', $query['client_id']);
        self::assertSame(self::REDIRECT, $query['redirect_uri']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('email,public_profile', $query['scope']);
        self::assertSame('st4te', $query['state']);
    }

    public function testFetchProfileExchangesCodeAndSignsGraphCall(): void
    {
        $calls = [];
        $this->http->method('get')->willReturnCallback(
            function (string $url, array $query = [], ?string $bearer = null) use (&$calls): array {
                $calls[] = [$url, $query, $bearer];

                return str_ends_with($url, '/oauth/access_token')
                    ? ['access_token' => 'tok']
                    : ['id' => '77', 'email' => 'm@x.it', 'first_name' => 'Mario', 'last_name' => 'Rossi'];
            }
        );

        $profile = $this->provider->fetchProfile('the-code');

        self::assertEquals(new Profile('facebook', '77', 'm@x.it', true, 'Mario', 'Rossi'), $profile);
        self::assertSame('https://graph.facebook.com/v24.0/oauth/access_token', $calls[0][0]);
        self::assertSame([
            'client_id' => 'appid',
            'redirect_uri' => self::REDIRECT,
            'client_secret' => 'appsecret',
            'code' => 'the-code',
        ], $calls[0][1]);
        self::assertSame('https://graph.facebook.com/v24.0/me', $calls[1][0]);
        self::assertSame('id,email,first_name,last_name', $calls[1][1]['fields']);
        self::assertSame(hash_hmac('sha256', 'tok', 'appsecret'), $calls[1][1]['appsecret_proof']);
        self::assertSame('tok', $calls[1][2]);
    }

    public function testNormalizeWithoutEmail(): void
    {
        $profile = $this->provider->normalizeProfile(['id' => '77', 'first_name' => 'Mario', 'last_name' => 'Rossi']);

        self::assertNull($profile->email);
        self::assertFalse($profile->emailVerified);
    }

    public function testNormalizeFallsBackToName(): void
    {
        $profile = $this->provider->normalizeProfile(['id' => '77', 'email' => 'm@x.it', 'name' => 'Mario Rossi']);

        self::assertSame('Mario', $profile->firstname);
        self::assertSame('Rossi', $profile->lastname);
    }

    public function testNormalizeWithoutIdThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->provider->normalizeProfile(['email' => 'm@x.it']);
    }
}
