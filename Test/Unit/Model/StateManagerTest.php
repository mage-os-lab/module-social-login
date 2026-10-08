<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\StateManager;
use Digitalway\SocialLogin\Test\Unit\Fake\InMemorySessionBag;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
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

    public function testFailedAttemptWithWrongProviderInvalidatesThatState(): void
    {
        $this->manager->create('google', '');
        try {
            $this->manager->consume('facebook', 'abc123');
            self::fail('Expected LocalizedException on wrong provider');
        } catch (LocalizedException) {
            // expected
        }

        $this->expectException(LocalizedException::class);
        $this->manager->consume('google', 'abc123');
    }

    public function testFailedAttemptWithUnknownStateDoesNotInvalidateValidState(): void
    {
        $this->manager->create('google', 'https://shop.test/checkout/');

        try {
            $this->manager->consume('google', 'nope');
            self::fail('Expected LocalizedException on wrong state');
        } catch (LocalizedException) {
            // expected
        }

        self::assertSame('https://shop.test/checkout/', $this->manager->consume('google', 'abc123'));
    }

    public function testConcurrentProviderFlowsAreIndependentlyValid(): void
    {
        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturnOnConsecutiveCalls('hash_google', 'hash_linkedin');
        $manager = new StateManager($this->bag, $random);

        $googleState = $manager->create('google', 'https://shop.test/cart');
        $linkedinState = $manager->create('linkedin', 'https://shop.test/checkout');

        self::assertSame('hash_google', $googleState);
        self::assertSame('hash_linkedin', $linkedinState);

        // First callback returns Google flow
        $googleUrl = $manager->consume('google', 'hash_google');
        self::assertSame('https://shop.test/cart', $googleUrl);

        // Second callback returns LinkedIn flow
        $linkedinUrl = $manager->consume('linkedin', 'hash_linkedin');
        self::assertSame('https://shop.test/checkout', $linkedinUrl);
    }

    public function testConcurrentFlowsInReverseCallbackOrder(): void
    {
        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturnOnConsecutiveCalls('hash_google', 'hash_linkedin');
        $manager = new StateManager($this->bag, $random);

        $manager->create('google', 'https://shop.test/cart');
        $manager->create('linkedin', 'https://shop.test/checkout');

        // LinkedIn callback arrives first
        self::assertSame('https://shop.test/checkout', $manager->consume('linkedin', 'hash_linkedin'));

        // Google callback arrives second
        self::assertSame('https://shop.test/cart', $manager->consume('google', 'hash_google'));
    }

    public function testBoundedMapPrunesOldestWhenLimitReached(): void
    {
        $hashes = array_map(fn($i) => "hash_$i", range(1, 15));
        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturnOnConsecutiveCalls(...$hashes);
        $manager = new StateManager($this->bag, $random);

        for ($i = 1; $i <= 12; $i++) {
            $manager->create('google', "/url-$i");
        }

        // Oldest (hash_1 and hash_2) should have been pruned because MAX_STATES = 10
        try {
            $manager->consume('google', 'hash_1');
            self::fail('Expected hash_1 to be pruned');
        } catch (LocalizedException) {
            // expected
        }

        try {
            $manager->consume('google', 'hash_2');
            self::fail('Expected hash_2 to be pruned');
        } catch (LocalizedException) {
            // expected
        }

        // Newest (hash_12, hash_3) should still be valid
        self::assertSame('/url-12', $manager->consume('google', 'hash_12'));
        self::assertSame('/url-3', $manager->consume('google', 'hash_3'));
    }

    public function testExpiredStateIsRejected(): void
    {
        $now = 1000;
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(function () use (&$now) {
            return $now;
        });

        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturn('abc123');

        $manager = new StateManager($this->bag, $random, $dateTime);
        $manager->create('google', 'https://shop.test/');

        $now += StateManager::TTL_SECONDS + 1;

        $this->expectException(LocalizedException::class);
        $manager->consume('google', 'abc123');
    }

    public function testDiscardRemovesAllStatesWhenNoArgumentsGiven(): void
    {
        $this->manager->create('google', '');
        $this->manager->discard();

        self::assertSame([], $this->bag->data);
    }

    public function testDiscardSpecificStateOnlyRemovesThatState(): void
    {
        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturnOnConsecutiveCalls('hash_google', 'hash_linkedin');
        $manager = new StateManager($this->bag, $random);

        $manager->create('google', 'https://shop.test/cart');
        $manager->create('linkedin', 'https://shop.test/checkout');

        $manager->discard('hash_google');

        try {
            $manager->consume('google', 'hash_google');
            self::fail('Expected hash_google to be discarded');
        } catch (LocalizedException) {
            // expected
        }

        self::assertSame('https://shop.test/checkout', $manager->consume('linkedin', 'hash_linkedin'));
    }

    public function testBackwardCompatibilityWithSingleStateSession(): void
    {
        $this->bag->set(StateManager::KEY, [
            'state' => 'legacy_hash',
            'provider' => 'google',
            'return_url' => 'https://shop.test/legacy',
        ]);

        self::assertSame('https://shop.test/legacy', $this->manager->consume('google', 'legacy_hash'));
    }
}
