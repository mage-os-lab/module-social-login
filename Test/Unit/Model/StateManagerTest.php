<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\StateManager;
use Digitalway\SocialLogin\Test\Unit\Fake\InMemorySessionBag;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StateManagerTest extends TestCase
{
    private InMemorySessionBag $bag;
    private StateManager $manager;

    protected function setUp(): void
    {
        $this->bag = new InMemorySessionBag();
        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturn('abc123');
        $this->manager = new StateManager($this->bag, $random);
    }

    public function testConsumeReturnsReturnUrl(): void
    {
        $state = $this->manager->create('google', 'https://shop.test/checkout/');

        self::assertSame('abc123', $state);
        self::assertSame('https://shop.test/checkout/', $this->manager->consume('google', 'abc123'));
    }

    public function testStateCanBeConsumedOnlyOnce(): void
    {
        $this->manager->create('google', '');
        $this->manager->consume('google', 'abc123');

        $this->expectException(LocalizedException::class);
        $this->manager->consume('google', 'abc123');
    }

    public function testWrongStateIsRejected(): void
    {
        $this->manager->create('google', '');

        $this->expectException(LocalizedException::class);
        $this->manager->consume('google', 'nope');
    }

    public function testStateIssuedForAnotherProviderIsRejected(): void
    {
        $this->manager->create('google', '');

        $this->expectException(LocalizedException::class);
        $this->manager->consume('facebook', 'abc123');
    }

    public function testEmptyStateIsRejected(): void
    {
        $this->manager->create('google', '');

        $this->expectException(LocalizedException::class);
        $this->manager->consume('google', '');
    }

    public function testFailedAttemptInvalidatesStoredState(): void
    {
        $this->manager->create('google', '');
        try {
            $this->manager->consume('google', 'nope');
        } catch (LocalizedException) {
            // expected
        }

        $this->expectException(LocalizedException::class);
        $this->manager->consume('google', 'abc123');
    }

    public function testDiscardRemovesState(): void
    {
        $this->manager->create('google', '');
        $this->manager->discard();

        self::assertSame([], $this->bag->data);
    }
}
