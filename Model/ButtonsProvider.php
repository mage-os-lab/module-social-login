<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Digitalway\SocialLogin\Api\ProviderIconInterface;
use Digitalway\SocialLogin\Api\ProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Button data (login/registration pages and checkout popup).
 */
class ButtonsProvider
{
    private const REFERER_PATTERN = '/^[A-Za-z0-9\-_,~]+$/';
    private const ABSOLUTE_URL_PATTERN = '#^(https?:)?//#i';

    /**
     * @param array<string, string> $icons Icon per provider code (asset ID or absolute URL), set in di.xml;
     *                                     overrides the icon of the provider
     */
    public function __construct(
        private readonly ProviderPool $providerPool,
        private readonly UrlInterface $url,
        private readonly AssetRepository $assetRepository,
        private readonly array $icons = []
    ) {
    }

    /**
     * @param string $encodedReferer Return URL already encoded with Magento\Framework\Url\EncoderInterface
     * @return list<array{code: string, label: string, text: string, url: string, icon: string}>
     */
    public function getButtons(string $encodedReferer = ''): array
    {
        $buttons = [];
        foreach ($this->providerPool->getEnabled() as $provider) {
            $code = $provider->getCode();
            $params = ['provider' => $code];
            if ($encodedReferer !== '' && preg_match(self::REFERER_PATTERN, $encodedReferer) === 1) {
                $params['referer'] = $encodedReferer;
            }

            $buttons[] = [
                'code' => $code,
                'label' => $provider->getLabel(),
                'text' => (string) __('Continue with %1', $provider->getLabel()),
                'url' => $this->url->getUrl('sociallogin/account/redirect', $params),
                'icon' => $this->getIconUrl($provider),
            ];
        }

        return $buttons;
    }

    /**
     * Icon order: "icons" argument in di.xml, then ProviderIconInterface, then the bundled icon.
     */
    private function getIconUrl(ProviderInterface $provider): string
    {
        $code = $provider->getCode();
        $icon = trim((string) ($this->icons[$code] ?? ''));
        if ($icon === '' && $provider instanceof ProviderIconInterface) {
            $icon = trim($provider->getIcon());
        }
        if ($icon === '') {
            $icon = 'Digitalway_SocialLogin::images/' . $code . '.svg';
        }

        return preg_match(self::ABSOLUTE_URL_PATTERN, $icon) === 1
            ? $icon
            : $this->assetRepository->getUrl($icon);
    }
}
