<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Customer\Api\Data\CustomerInterface;

final class LinkResult
{
    private function __construct(public readonly ?CustomerInterface $customer)
    {
    }

    public static function loggedIn(CustomerInterface $customer): self
    {
        return new self($customer);
    }

    /** Customer input needed (missing/unverified email or missing names). */
    public static function pending(): self
    {
        return new self(null);
    }

    public function isPending(): bool
    {
        return $this->customer === null;
    }
}
