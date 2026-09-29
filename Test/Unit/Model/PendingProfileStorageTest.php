<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Test\Unit\Fake\InMemorySessionBag;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class PendingProfileStorageTest extends TestCase
{
    private InMemorySessionBag $bag;
    private int $now = 1_000_000;
    private PendingProfileStorage $storage;

    protected function setUp(): void
    {
        $this->bag = new InMemorySessionBag();
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn (): int => $this->now);
        $this->storage = new PendingProfileStorage($this->bag, $dateTime);
    }

    public function testSaveAndGet(): void
    {
        $profile = new Profile('instagram', '555', null, false, 'mario', '');
        $this->storage->save($profile, 'https://shop.test/checkout/');

        $pending = $this->storage->get();

        self::assertNotNull($pending);
        self::assertEquals($profile, $pending->profile);
        self::assertSame('https://shop.test/checkout/', $pending->returnUrl);
        self::assertNull($pending->claimedEmail);
    }

    public function testStillValidAtExactlyTtl(): void
    {
        $this->storage->save(new Profile('instagram', '555', null, false, '', ''), '');
        $this->now += PendingProfileStorage::TTL_SECONDS;

        self::assertNotNull($this->storage->get());
    }

    public function testExpiredPendingIsDiscarded(): void
    {
        $this->storage->save(new Profile('instagram', '555', null, false, '', ''), '');
        $this->now += PendingProfileStorage::TTL_SECONDS + 1;

        self::assertNull($this->storage->get());
        self::assertSame([], $this->bag->data);
    }

    public function testClaimedEmailIsPersisted(): void
    {
        $this->storage->save(new Profile('instagram', '555', null, false, '', ''), '');
        $this->storage->setClaimedEmail('m@x.it');

        self::assertSame('m@x.it', $this->storage->get()?->claimedEmail);
    }

    public function testClear(): void
    {
        $this->storage->save(new Profile('instagram', '555', null, false, '', ''), '');
        $this->storage->clear();

        self::assertNull($this->storage->get());
    }

    public function testCorruptedDataIsDiscarded(): void
    {
        $this->bag->data['pending_profile'] = ['profile' => ['provider' => ''], 'created_at' => $this->now];

        self::assertNull($this->storage->get());
        self::assertSame([], $this->bag->data);
    }
}
