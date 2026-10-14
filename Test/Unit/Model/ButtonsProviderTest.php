<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Api\ProviderIconInterface;
use Digitalway\SocialLogin\Api\ProviderInterface;
use Digitalway\SocialLogin\Model\ButtonsProvider;
use Digitalway\SocialLogin\Model\ProviderPool;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ButtonsProviderTest extends TestCase
{
    private ButtonsProvider $buttonsProvider;

    protected function setUp(): void
    {
        $google = $this->createMock(ProviderInterface::class);
        $google->method('getCode')->willReturn('google');
        $google->method('getLabel')->willReturn('Google');

        $this->buttonsProvider = $this->buttonsProvider([$google]);
    }

    /**
     * @param list<ProviderInterface> $providers
     * @param array<string, string> $icons
     */
    private function buttonsProvider(array $providers, array $icons = []): ButtonsProvider
    {
        $pool = $this->createMock(ProviderPool::class);
        $pool->method('getEnabled')->willReturn($providers);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string => 'https://shop.test/' . $route . '?' . http_build_query($params)
        );
        $assets = $this->createMock(AssetRepository::class);
        $assets->method('getUrl')->willReturnCallback(static fn (string $file): string => 'https://shop.test/static/' . $file);

        return new ButtonsProvider($pool, $url, $assets, $icons);
    }

    private function providerWithIcon(string $code, string $icon): ProviderInterface
    {
        $provider = $this->createMockForIntersectionOfInterfaces(
            [ProviderInterface::class, ProviderIconInterface::class]
        );
        $provider->method('getCode')->willReturn($code);
        $provider->method('getLabel')->willReturn(ucfirst($code));
        $provider->method('getIcon')->willReturn($icon);

        return $provider;
    }

    public function testButtonsForEnabledProviders(): void
    {
        $buttons = $this->buttonsProvider->getButtons();

        self::assertCount(1, $buttons);
        self::assertSame('google', $buttons[0]['code']);
        self::assertSame('Google', $buttons[0]['label']);
        self::assertSame('https://shop.test/sociallogin/account/redirect?provider=google', $buttons[0]['url']);
        self::assertSame('https://shop.test/static/Digitalway_SocialLogin::images/google.svg', $buttons[0]['icon']);
        self::assertNotSame('', $buttons[0]['text']);
    }

    public function testValidRefererIsPassedThrough(): void
    {
        $buttons = $this->buttonsProvider->getButtons('aHR0cHM6Ly9zaG9wLnRlc3QvY2hlY2tvdXQv');

        self::assertStringContainsString('referer=aHR0cHM6Ly9zaG9wLnRlc3QvY2hlY2tvdXQv', $buttons[0]['url']);
    }

    public function testRefererWithUnexpectedCharactersIsDropped(): void
    {
        $buttons = $this->buttonsProvider->getButtons('abc/../../evil');

        self::assertStringNotContainsString('referer', $buttons[0]['url']);
    }

    public function testRefererWithMagentoTildePaddingIsPassedThrough(): void
    {
        $encodedWithTilde = 'aHR0cHM6Ly9leGFtcGxlLmNvbS9jaGVja291dC8~';
        $buttons = $this->buttonsProvider->getButtons($encodedWithTilde);

        self::assertStringContainsString('referer=' . urlencode($encodedWithTilde), $buttons[0]['url']);
    }

    public function testProviderCanSupplyItsOwnIcon(): void
    {
        $buttons = $this->buttonsProvider([$this->providerWithIcon('github', 'Vendor_GitHubLogin::images/github.svg')])
            ->getButtons();

        self::assertSame('https://shop.test/static/Vendor_GitHubLogin::images/github.svg', $buttons[0]['icon']);
    }

    public function testEmptyProviderIconFallsBackToModuleIcon(): void
    {
        $buttons = $this->buttonsProvider([$this->providerWithIcon('google', '')])->getButtons();

        self::assertSame('https://shop.test/static/Digitalway_SocialLogin::images/google.svg', $buttons[0]['icon']);
    }

    public function testConfiguredIconOverridesProviderIcon(): void
    {
        $buttons = $this->buttonsProvider(
            [$this->providerWithIcon('github', 'Vendor_GitHubLogin::images/github.svg')],
            ['github' => 'Vendor_Theme::images/github-dark.svg']
        )->getButtons();

        self::assertSame('https://shop.test/static/Vendor_Theme::images/github-dark.svg', $buttons[0]['icon']);
    }

    public function testConfiguredIconOverridesBundledProvider(): void
    {
        $google = $this->createMock(ProviderInterface::class);
        $google->method('getCode')->willReturn('google');
        $google->method('getLabel')->willReturn('Google');

        $buttons = $this->buttonsProvider([$google], ['google' => 'Vendor_Theme::images/google.svg'])->getButtons();

        self::assertSame('https://shop.test/static/Vendor_Theme::images/google.svg', $buttons[0]['icon']);
    }

    public function testAbsoluteIconUrlIsUsedAsIs(): void
    {
        $buttons = $this->buttonsProvider(
            [$this->providerWithIcon('github', 'https://cdn.example.com/github.svg')],
            ['google' => '//cdn.example.com/google.svg']
        )->getButtons();

        self::assertSame('https://cdn.example.com/github.svg', $buttons[0]['icon']);
    }

    public function testProtocolRelativeConfiguredIconIsUsedAsIs(): void
    {
        $buttons = $this->buttonsProvider(
            [$this->providerWithIcon('google', '')],
            ['google' => '//cdn.example.com/google.svg']
        )->getButtons();

        self::assertSame('//cdn.example.com/google.svg', $buttons[0]['icon']);
    }
}
