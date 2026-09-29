<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Block;

use Digitalway\SocialLogin\Model\PendingLogin;
use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\ProviderPool;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class CompleteEmail extends Template
{
    private ?PendingLogin $pending = null;
    private bool $loaded = false;

    public function __construct(
        Context $context,
        private readonly PendingProfileStorage $pendingStorage,
        private readonly ProviderPool $providerPool,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getPending(): ?PendingLogin
    {
        if (!$this->loaded) {
            $this->pending = $this->pendingStorage->get();
            $this->loaded = true;
        }

        return $this->pending;
    }

    public function getProviderLabel(): string
    {
        $code = $this->getPending()?->profile->provider ?? '';

        return $this->providerPool->get($code)?->getLabel() ?? ucfirst($code);
    }

    public function getEmail(): string
    {
        $pending = $this->getPending();

        return (string) ($pending?->claimedEmail ?? $pending?->profile->email ?? '');
    }

    public function getFirstname(): string
    {
        return (string) $this->getPending()?->profile->firstname;
    }

    public function getLastname(): string
    {
        return (string) $this->getPending()?->profile->lastname;
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('sociallogin/account/completeemailpost');
    }
}
