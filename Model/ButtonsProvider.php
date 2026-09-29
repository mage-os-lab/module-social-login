<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Button data (login/registration pages and checkout popup).
 */
class ButtonsProvider
{
    private const REFERER_PATTERN = '/^[A-Za-z0-9\-_,]+$/';

    public function __construct(
        private readonly ProviderPool $providerPool,
        private readonly UrlInterface $url,
        private readonly AssetRepository $assetRepository
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
                'icon' => $this->assetRepository->getUrl('Digitalway_SocialLogin::images/' . $code . '.svg'),
            ];
        }

        return $buttons;
    }
}
