<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model\Provider;

use Digitalway\SocialLogin\Model\Config;
use Digitalway\SocialLogin\Model\Http\HttpClient;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\Provider\Instagram;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class InstagramTest extends TestCase
{
    private const REDIRECT = 'https://shop.test/sociallogin/account/callback/provider/instagram/';

    private HttpClient&MockObject $http;
    private Instagram $provider;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getClientId')->willReturn('igid');
        $config->method('getClientSecret')->willReturn('igsecret');
        $config->method('getRedirectUri')->willReturn(self::REDIRECT);
        $config->method('getGraphVersion')->willReturn('v24.0');
        $this->http = $this->createMock(HttpClient::class);
        $this->provider = new Instagram($config, $this->http);
    }

    public function testCodeAndLabel(): void
    {
        self::assertSame('instagram', $this->provider->getCode());
        self::assertSame('Instagram', $this->provider->getLabel());
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl('st4te');

        self::assertStringStartsWith('https://www.instagram.com/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('igid', $query['client_id']);
        self::assertSame(self::REDIRECT, $query['redirect_uri']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('instagram_business_basic', $query['scope']);
        self::assertSame('st4te', $query['state']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function tokenResponses(): array
    {
        return [
            'flat format' => [['access_token' => 'tok', 'user_id' => 1]],
            'data[] format' => [['data' => [['access_token' => 'tok', 'user_id' => '1', 'permissions' => 'instagram_business_basic']]]],
        ];
    }

    /**
     * @param array<string, mixed> $tokenResponse
     */
    #[DataProvider('tokenResponses')]
    public function testFetchProfileAcceptsBothTokenFormats(array $tokenResponse): void
    {
        $this->http->expects(self::once())->method('postForm')
            ->with('https://api.instagram.com/oauth/access_token', [
                'client_id' => 'igid',
                'client_secret' => 'igsecret',
                'grant_type' => 'authorization_code',
                'redirect_uri' => self::REDIRECT,
                'code' => 'the-code',
            ])
            ->willReturn($tokenResponse);
        $this->http->expects(self::once())->method('get')
            ->with('https://graph.instagram.com/v24.0/me', ['fields' => 'id,username,name', 'access_token' => 'tok'])
            ->willReturn(['id' => '555', 'username' => 'mario.shop', 'name' => 'Mario Rossi']);

        self::assertEquals(
            new Profile('instagram', '555', null, false, 'Mario', 'Rossi'),
            $this->provider->fetchProfile('the-code')
        );
    }

    public function testNormalizeWithoutNameUsesUsername(): void
    {
        $profile = $this->provider->normalizeProfile(['id' => '555', 'username' => 'mario.shop']);

        self::assertSame('mario.shop', $profile->firstname);
        self::assertSame('', $profile->lastname);
        self::assertNull($profile->email);
        self::assertFalse($profile->emailVerified);
    }
}
