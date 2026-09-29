<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;

/**
 * What happens once the profile has been obtained from the provider (shared by
 * the OAuth callback and the developer-mode simulation controller).
 */
class ProfileLoginFlow
{
    public function __construct(
        private readonly CustomerLinker $linker,
        private readonly LoginHandler $loginHandler,
        private readonly PendingProfileStorage $pendingStorage,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @return string URL to redirect to
     * @throws LocalizedException
     */
    public function complete(Profile $profile, string $returnUrl): string
    {
        $result = $this->linker->resolve($profile);

        if ($result->isPending()) {
            $this->pendingStorage->save($profile, $returnUrl);

            return $this->url->getUrl('sociallogin/account/completeemail');
        }

        $this->loginHandler->login($result->customer);

        return $this->redirectUrl($returnUrl);
    }

    public function redirectUrl(string $returnUrl): string
    {
        return $returnUrl !== '' ? $returnUrl : $this->url->getUrl('customer/account');
    }
}
