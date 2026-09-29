<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Api\ProviderInterface;
use Digitalway\SocialLogin\Model\ProviderPool;
use PHPUnit\Framework\TestCase;

final class ProviderPoolTest extends TestCase
{
    public function testGetReturnsProviderByCodeOrNull(): void
    {
        $google = $this->provider(true);
        $pool = new ProviderPool(['google' => $google]);

        self::assertSame($google, $pool->get('google'));
        self::assertNull($pool->get('myspace'));
        self::assertNull($pool->get(''));
    }

    public function testGetEnabledFiltersAndKeepsOrder(): void
    {
        $google = $this->provider(true);
        $facebook = $this->provider(false);
        $linkedin = $this->provider(true);
        $pool = new ProviderPool(['google' => $google, 'facebook' => $facebook, 'linkedin' => $linkedin]);

        self::assertSame([$google, $linkedin], $pool->getEnabled());
    }

    private function provider(bool $enabled): ProviderInterface
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('isEnabled')->willReturn($enabled);

        return $provider;
    }
}
