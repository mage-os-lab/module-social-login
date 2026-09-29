<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Block\Adminhtml\System\Config;

use Digitalway\SocialLogin\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shows, read-only, the Redirect URI to register in the provider console.
 * No input: the value is not saved.
 */
class RedirectUri extends Field
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $path = (string) ($element->getData('field_config')['path'] ?? '');
        $code = substr($path, (int) strrpos($path, '/') + 1);
        $uri = $this->config->getRedirectUri($code, $this->resolveStoreId());

        return '<code style="user-select:all;word-break:break-all">' . $this->escapeHtml($uri) . '</code>';
    }

    private function resolveStoreId(): ?int
    {
        $websiteId = (int) $this->getRequest()->getParam('website');
        if ($websiteId > 0) {
            return (int) $this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        }

        $default = $this->storeManager->getDefaultStoreView();

        return $default ? (int) $default->getId() : null;
    }
}
