<?php
declare(strict_types=1);

namespace MageOS\SocialLogin\Api;

/**
 * Optional: implemented by providers that ship their own button icon.
 *
 * Without it (or with an empty value) the icon is MageOS_SocialLogin::images/<code>.svg.
 * The "icons" argument of MageOS\SocialLogin\Model\ButtonsProvider in di.xml overrides both.
 */
interface ProviderIconInterface
{
    /** Asset ID (Vendor_Module::images/icon.svg) or absolute URL (https://... or //...). */
    public function getIcon(): string;
}
