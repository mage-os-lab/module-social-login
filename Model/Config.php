<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    public const SECTION = 'digitalway_sociallogin';
    public const CALLBACK_PATH = 'sociallogin/account/callback/provider/';
    public const DEFAULT_GRAPH_VERSION = 'v24.0';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isModuleEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::SECTION . '/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function isProviderEnabled(string $code): bool
    {
        return $this->scopeConfig->isSetFlag(self::SECTION . '/' . $code . '/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getClientId(string $code): string
    {
        return trim((string) $this->value($code, 'client_id'));
    }

    public function getClientSecret(string $code): string
    {
        $encrypted = (string) $this->value($code, 'client_secret');

        return $encrypted === '' ? '' : trim($this->encryptor->decrypt($encrypted));
    }

    public function getGraphVersion(string $code): string
    {
        $version = trim((string) $this->value($code, 'graph_version'));

        return $version !== '' ? $version : self::DEFAULT_GRAPH_VERSION;
    }

    /**
     * Must be identical in the authorization request and in the code exchange,
     * and match the one registered in the provider console.
     */
    public function getRedirectUri(string $code, ?int $storeId = null): string
    {
        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_LINK, true);

        return $baseUrl . self::CALLBACK_PATH . $code . '/';
    }

    private function value(string $code, string $field): mixed
    {
        return $this->scopeConfig->getValue(self::SECTION . '/' . $code . '/' . $field, ScopeInterface::SCOPE_STORE);
    }
}
