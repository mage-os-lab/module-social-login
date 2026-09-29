<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Block\Checkout;

use Digitalway\SocialLogin\Block\Checkout\LayoutProcessor;
use Digitalway\SocialLogin\Model\ButtonsProvider;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class LayoutProcessorTest extends TestCase
{
    private ButtonsProvider&MockObject $buttonsProvider;
    private LayoutProcessor $processor;

    protected function setUp(): void
    {
        $this->buttonsProvider = $this->createMock(ButtonsProvider::class);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('checkout')->willReturn('https://shop.test/checkout/');
        $encoder = $this->createMock(EncoderInterface::class);
        $encoder->method('encode')->with('https://shop.test/checkout/')->willReturn('ENCODED');

        $this->processor = new LayoutProcessor($this->buttonsProvider, $url, $encoder);
    }

    public function testAddsComponentToAuthenticationPopup(): void
    {
        $buttons = [['code' => 'google', 'label' => 'Google', 'text' => 'Continue with Google', 'url' => 'u', 'icon' => 'i']];
        $this->buttonsProvider->method('getButtons')->with('ENCODED')->willReturn($buttons);

        $result = $this->processor->process($this->jsLayout());

        $component = $result['components']['checkout']['children']['authentication']['children']['social-login'];
        self::assertSame('Digitalway_SocialLogin/js/view/social-buttons', $component['component']);
        self::assertSame('before', $component['displayArea']);
        self::assertSame($buttons, $component['config']['buttons']);
    }

    public function testNoEnabledProvidersLeavesLayoutUntouched(): void
    {
        $this->buttonsProvider->method('getButtons')->willReturn([]);

        self::assertSame($this->jsLayout(), $this->processor->process($this->jsLayout()));
    }

    public function testMissingAuthenticationComponentLeavesLayoutUntouched(): void
    {
        $this->buttonsProvider->expects(self::never())->method('getButtons');
        $layout = ['components' => ['checkout' => ['children' => []]]];

        self::assertSame($layout, $this->processor->process($layout));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsLayout(): array
    {
        return ['components' => ['checkout' => ['children' => ['authentication' => ['children' => []]]]]];
    }
}
