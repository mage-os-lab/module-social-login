<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Fake;

use Digitalway\SocialLogin\Model\SessionBag;

/**
 * In-memory SessionBag for tests (does not call the parent constructor).
 */
class InMemorySessionBag extends SessionBag
{
    /** @var array<string, mixed> */
    public array $data = [];

    public function __construct()
    {
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function pull(string $key): mixed
    {
        $value = $this->data[$key] ?? null;
        unset($this->data[$key]);

        return $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }
}
