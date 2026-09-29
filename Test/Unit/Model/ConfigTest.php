<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;
    private StoreManagerInterface&MockObject $storeManager;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->config = new Config($this->scopeConfig, $this->encryptor, $this->storeManager);
    }

    public function testProviderFlagsAndCredentialsAreReadPerProvider(): void
    {
        $flags = [
            'digitalway_sociallogin/general/enabled' => true,
            'digitalway_sociallogin/google/enabled' => true,
            'digitalway_sociallogin/facebook/enabled' => false,
        ];
        $values = [
            'digitalway_sociallogin/google/client_id' => '  cid  ',
            'digitalway_sociallogin/google/client_secret' => 'ENCRYPTED',
        ];
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static function (string $path, string $scope) use ($flags): bool {
                self::assertSame(ScopeInterface::SCOPE_STORE, $scope);

                return $flags[$path] ?? false;
            }
        );
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $values[$path] ?? null
        );
        $this->encryptor->method('decrypt')->with('ENCRYPTED')->willReturn('plain-secret');

        self::assertTrue($this->config->isModuleEnabled());
        self::assertTrue($this->config->isProviderEnabled('google'));
        self::assertFalse($this->config->isProviderEnabled('facebook'));
        self::assertSame('cid', $this->config->getClientId('google'));
        self::assertSame('plain-secret', $this->config->getClientSecret('google'));
    }

    public function testEmptySecretIsNotDecrypted(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->encryptor->expects(self::never())->method('decrypt');

        self::assertSame('', $this->config->getClientSecret('linkedin'));
    }

    public function testGraphVersionFallsBackToDefault(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        self::assertSame(Config::DEFAULT_GRAPH_VERSION, $this->config->getGraphVersion('facebook'));
    }

    public function testRedirectUriUsesSecureLinkBaseUrl(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_LINK, true)->willReturn('https://shop.test/it/');
        $this->storeManager->method('getStore')->with(3)->willReturn($store);

        self::assertSame(
            'https://shop.test/it/sociallogin/account/callback/provider/google/',
            $this->config->getRedirectUri('google', 3)
        );
    }
}
