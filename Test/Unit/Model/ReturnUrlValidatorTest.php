<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\ReturnUrlValidator;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\HostChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ReturnUrlValidatorTest extends TestCase
{
    private ReturnUrlValidator $validator;

    protected function setUp(): void
    {
        $hostChecker = $this->createMock(HostChecker::class);
        $hostChecker->method('isOwnOrigin')->willReturnCallback(
            static fn (string $url): bool => parse_url($url, PHP_URL_HOST) === 'shop.test'
        );
        $decoder = $this->createMock(DecoderInterface::class);
        $decoder->method('decode')->willReturnCallback(
            static fn (string $value): string => (string) base64_decode(strtr($value, '-_,', '+/='))
        );
        $this->validator = new ReturnUrlValidator($hostChecker, $decoder);
    }

    public function testInternalUrlIsAccepted(): void
    {
        self::assertSame('https://shop.test/checkout/', $this->validator->sanitize('https://shop.test/checkout/'));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function rejectedUrls(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'external host' => ['https://evil.test/phish'],
            'protocol-relative' => ['//evil.test/phish'],
            'javascript' => ['javascript:alert(1)'],
            'ftp scheme' => ['ftp://shop.test/file'],
            'relative path' => ['/customer/account'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectedUrls(?string $url): void
    {
        self::assertSame('', $this->validator->sanitize($url));
    }

    public function testEncodedRefererIsDecodedAndValidated(): void
    {
        $encode = static fn (string $url): string => strtr(base64_encode($url), '+/=', '-_,');

        self::assertSame('https://shop.test/checkout/', $this->validator->fromEncodedReferer($encode('https://shop.test/checkout/')));
        self::assertSame('', $this->validator->fromEncodedReferer($encode('https://evil.test/')));
        self::assertSame('', $this->validator->fromEncodedReferer(''));
    }
}
