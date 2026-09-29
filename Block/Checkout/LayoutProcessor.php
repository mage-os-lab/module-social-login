<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Block\Checkout;

use Digitalway\SocialLogin\Model\ButtonsProvider;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;

/**
 * Adds the social buttons to the checkout "authentication" popup
 * ("before" region of Magento_Checkout/authentication.html).
 */
class LayoutProcessor implements LayoutProcessorInterface
{
    public function __construct(
        private readonly ButtonsProvider $buttonsProvider,
        private readonly UrlInterface $url,
        private readonly EncoderInterface $urlEncoder
    ) {
    }

    /**
     * @param array<string, mixed> $jsLayout
     * @return array<string, mixed>
     */
    public function process($jsLayout)
    {
        if (!isset($jsLayout['components']['checkout']['children']['authentication'])) {
            return $jsLayout;
        }

        $buttons = $this->buttonsProvider->getButtons($this->urlEncoder->encode($this->url->getUrl('checkout')));
        if ($buttons === []) {
            return $jsLayout;
        }

        $jsLayout['components']['checkout']['children']['authentication']['children']['social-login'] = [
            'component' => 'Digitalway_SocialLogin/js/view/social-buttons',
            'displayArea' => 'before',
            'sortOrder' => 10,
            'config' => ['buttons' => $buttons],
        ];

        return $jsLayout;
    }
}
