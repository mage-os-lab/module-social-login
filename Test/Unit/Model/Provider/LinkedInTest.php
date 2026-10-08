<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model\Provider;

use Digitalway\SocialLogin\Model\Config;
use Digitalway\SocialLogin\Model\Http\HttpClient;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\Provider\LinkedIn;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LinkedInTest extends TestCase
{
    private const REDIRECT = 'https://shop.test/sociallogin/account/callback/provider/linkedin/';

    private HttpClient&MockObject $http;
    private LinkedIn $provider;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getClientId')->willReturn('lid');
        $config->method('getClientSecret')->willReturn('lsecret');
        $config->method('getRedirectUri')->willReturn(self::REDIRECT);
        $this->http = $this->createMock(HttpClient::class);
        $this->provider = new LinkedIn($config, $this->http);
    }

    public function testCodeAndLabel(): void
    {
        self::assertSame('linkedin', $this->provider->getCode());
        self::assertSame('LinkedIn', $this->provider->getLabel());
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl('st4te');

        self::assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('lid', $query['client_id']);
        self::assertSame(self::REDIRECT, $query['redirect_uri']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('openid profile email', $query['scope']);
        self::assertSame('st4te', $query['state']);
    }

    public function testFetchProfile(): void
    {
        $this->http->expects(self::once())->method('postForm')
            ->with('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => 'the-code',
                'client_id' => 'lid',
                'client_secret' => 'lsecret',
                'redirect_uri' => self::REDIRECT,
            ])
            ->willReturn(['access_token' => 'tok']);
        $this->http->expects(self::once())->method('get')
            ->with('https://api.linkedin.com/v2/userinfo', [], 'tok')
            ->willReturn([
                'sub' => 'abc',
                'email' => 'l@x.it',
                'email_verified' => true,
                'given_name' => 'Luca',
                'family_name' => 'Bianchi',
            ]);

        self::assertEquals(
            new Profile('linkedin', 'abc', 'l@x.it', true, 'Luca', 'Bianchi'),
            $this->provider->fetchProfile('the-code')
        );
    }

    public function testNormalizeUnverifiedEmail(): void
    {
        $profile = $this->provider->normalizeProfile([
            'sub' => 'abc', 'email' => 'l@x.it', 'email_verified' => false, 'given_name' => 'L', 'family_name' => 'B',
        ]);

        self::assertFalse($profile->emailVerified);
    }

    public function testNormalizeFallsBackToName(): void
    {
        $profile = $this->provider->normalizeProfile(['sub' => 'abc', 'name' => 'Luca Bianchi']);

        self::assertSame('Luca', $profile->firstname);
        self::assertSame('Bianchi', $profile->lastname);
        self::assertNull($profile->email);
    }
}
