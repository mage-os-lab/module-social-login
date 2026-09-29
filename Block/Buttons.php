<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Block;

use Digitalway\SocialLogin\Model\ButtonsProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Buttons extends Template
{
    public function __construct(
        Context $context,
        private readonly ButtonsProvider $buttonsProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The login page "referer" parameter (already encoded by the core) is passed through.
     *
     * @return list<array{code: string, label: string, text: string, url: string, icon: string}>
     */
    public function getButtons(): array
    {
        return $this->buttonsProvider->getButtons((string) $this->getRequest()->getParam('referer'));
    }
}
