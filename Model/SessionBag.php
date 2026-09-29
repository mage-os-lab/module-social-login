<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Customer\Model\Session as CustomerSession;

/**
 * Explicit adapter over the customer session (which exposes data only via
 * magic methods): prefixed keys and methods that can be mocked in tests.
 */
class SessionBag
{
    private const PREFIX = 'digitalway_sociallogin_';

    public function __construct(private readonly CustomerSession $session)
    {
    }

    public function get(string $key): mixed
    {
        return $this->session->getData(self::PREFIX . $key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->session->setData(self::PREFIX . $key, $value);
    }

    /**
     * Reads and removes in a single operation.
     */
    public function pull(string $key): mixed
    {
        return $this->session->getData(self::PREFIX . $key, true);
    }

    public function remove(string $key): void
    {
        $this->session->unsetData(self::PREFIX . $key);
    }
}
