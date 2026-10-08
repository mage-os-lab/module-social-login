<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model\Provider;

use Digitalway\SocialLogin\Model\Config;
use Digitalway\SocialLogin\Model\Http\HttpClient;
use Digitalway\SocialLogin\Model\Http\HttpException;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\Provider\Google;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GoogleTest extends TestCase
{
    private const REDIRECT = 'https://shop.test/sociallogin/account/callback/provider/google/';

    private HttpClient&MockObject $http;
    private Google $provider;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClient::class);
        $this->provider = new Google($this->config('cid', 'csecret'), $this->http);
    }

    public function testCodeAndLabel(): void
    {
        self::assertSame('google', $this->provider->getCode());
        self::assertSame('Google', $this->provider->getLabel());
    }

    public function testIsEnabledWhenModuleProviderAndCredentialsAreSet(): void
    {
        self::assertTrue($this->provider->isEnabled());
    }

    public function testIsDisabledWhenSecretIsMissing(): void
    {
        self::assertFalse((new Google($this->config('cid', ''), $this->http))->isEnabled());
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl('st4te');

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('cid', $query['client_id']);
        self::assertSame(self::REDIRECT, $query['redirect_uri']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('openid email profile', $query['scope']);
        self::assertSame('select_account', $query['prompt']);
        self::assertSame('st4te', $query['state']);
    }

    public function testFetchProfileExchangesCodeThenReadsUserinfo(): void
    {
        $this->http->expects(self::once())->method('postForm')
            ->with('https://oauth2.googleapis.com/token', [
                'code' => 'the-code',
                'client_id' => 'cid',
                'client_secret' => 'csecret',
                'redirect_uri' => self::REDIRECT,
                'grant_type' => 'authorization_code',
            ])
            ->willReturn(['access_token' => 'tok', 'token_type' => 'Bearer']);
        $this->http->expects(self::once())->method('get')
            ->with('https://openidconnect.googleapis.com/v1/userinfo', [], 'tok')
            ->willReturn([
                'sub' => '42',
                'email' => 'mario@example.com',
                'email_verified' => true,
                'given_name' => 'Mario',
                'family_name' => 'Rossi',
            ]);

        self::assertEquals(
            new Profile('google', '42', 'mario@example.com', true, 'Mario', 'Rossi'),
            $this->provider->fetchProfile('the-code')
        );
    }

    public function testFetchProfileWrapsHttpErrors(): void
    {
        $this->http->method('postForm')
            ->willThrowException(new HttpException('HTTP 400 from oauth2.googleapis.com (invalid_grant)'));

        $this->expectException(LocalizedException::class);
        $this->provider->fetchProfile('bad-code');
    }

    public function testFetchProfileWithoutAccessTokenFails(): void
    {
        $this->http->method('postForm')->willReturn(['token_type' => 'Bearer']);
        $this->http->expects(self::never())->method('get');

        $this->expectException(LocalizedException::class);
        $this->provider->fetchProfile('the-code');
    }

    public function testNormalizeUnverifiedEmail(): void
    {
        $profile = $this->provider->normalizeProfile([
            'sub' => '1', 'email' => 'a@b.it', 'email_verified' => false, 'given_name' => 'A', 'family_name' => 'B',
        ]);

        self::assertFalse($profile->emailVerified);
        self::assertSame('a@b.it', $profile->email);
    }

    public function testNormalizeAcceptsStringTrueForEmailVerified(): void
    {
        $profile = $this->provider->normalizeProfile([
            'sub' => '1', 'email' => 'a@b.it', 'email_verified' => 'true', 'given_name' => 'A', 'family_name' => 'B',
        ]);

        self::assertTrue($profile->emailVerified);
    }

    public function testNormalizeSingleWordName(): void
    {
        $profile = $this->provider->normalizeProfile([
            'sub' => '1', 'email' => 'cher@b.it', 'email_verified' => true, 'name' => 'Cher',
        ]);

        self::assertSame('Cher', $profile->firstname);
        self::assertSame('', $profile->lastname);
    }

    public function testNormalizeFallsBackToFullName(): void
    {
        $profile = $this->provider->normalizeProfile([
            'sub' => '1', 'email' => 'm@b.it', 'email_verified' => true, 'name' => 'Maria Grazia De Luca',
        ]);

        self::assertSame('Maria', $profile->firstname);
        self::assertSame('Grazia De Luca', $profile->lastname);
    }

    public function testNormalizeWithoutEmailGivesNullEmail(): void
    {
        $profile = $this->provider->normalizeProfile(['sub' => '1', 'email_verified' => true, 'name' => 'A B']);

        self::assertNull($profile->email);
        self::assertFalse($profile->emailVerified);
    }

    public function testNormalizeWithoutSubThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->provider->normalizeProfile(['email' => 'a@b.it']);
    }

    private function config(string $clientId, string $secret): Config&MockObject
    {
        $config = $this->createMock(Config::class);
        $config->method('isModuleEnabled')->willReturn(true);
        $config->method('isProviderEnabled')->willReturn(true);
        $config->method('getClientId')->willReturn($clientId);
        $config->method('getClientSecret')->willReturn($secret);
        $config->method('getRedirectUri')->willReturn(self::REDIRECT);

        return $config;
    }
}
