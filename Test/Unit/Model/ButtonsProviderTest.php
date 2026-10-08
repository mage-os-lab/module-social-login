<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

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
        $pool = $this->createMock(ProviderPool::class);
        $pool->method('getEnabled')->willReturn([$google]);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string => 'https://shop.test/' . $route . '?' . http_build_query($params)
        );
        $assets = $this->createMock(AssetRepository::class);
        $assets->method('getUrl')->willReturnCallback(static fn (string $file): string => 'https://shop.test/static/' . $file);

        $this->buttonsProvider = new ButtonsProvider($pool, $url, $assets);
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
}
