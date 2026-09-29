<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Observer;

use Digitalway\SocialLogin\Model\CustomerLinker;
use Digitalway\SocialLogin\Model\PendingLogin;
use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\ProviderPool;
use Digitalway\SocialLogin\Observer\LinkPendingIdentity;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class LinkPendingIdentityTest extends TestCase
{
    private PendingProfileStorage&MockObject $storage;
    private CustomerLinker&MockObject $linker;
    private ManagerInterface&MockObject $messages;
    private LinkPendingIdentity $observer;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(PendingProfileStorage::class);
        $this->linker = $this->createMock(CustomerLinker::class);
        $this->messages = $this->createMock(ManagerInterface::class);

        $this->observer = new LinkPendingIdentity(
            $this->storage,
            $this->linker,
            new ProviderPool([]),
            $this->messages,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testLinksWhenEmailDiffersOnlyByCase(): void
    {
        $this->storage->method('get')->willReturn($this->pending('Mario.Rossi@Example.com'));
        $this->linker->expects(self::once())->method('link')->with(
            7,
            new Profile('instagram', '555', 'Mario.Rossi@Example.com', false, 'Mario', 'Rossi')
        );
        $this->storage->expects(self::once())->method('clear');
        $this->messages->expects(self::once())->method('addSuccessMessage');

        $this->observer->execute($this->event('mario.rossi@example.com'));
    }

    public function testDifferentCustomerIsIgnoredAndPendingKept(): void
    {
        $this->storage->method('get')->willReturn($this->pending('mario@example.com'));
        $this->linker->expects(self::never())->method('link');
        $this->storage->expects(self::never())->method('clear');

        $this->observer->execute($this->event('someone.else@example.com'));
    }

    public function testPendingWithoutClaimedEmailIsIgnored(): void
    {
        $this->storage->method('get')->willReturn($this->pending(null));
        $this->linker->expects(self::never())->method('link');
        $this->storage->expects(self::never())->method('clear');

        $this->observer->execute($this->event('mario@example.com'));
    }

    public function testLinkErrorIsShownAndPendingCleared(): void
    {
        $this->storage->method('get')->willReturn($this->pending('mario@example.com'));
        $this->linker->method('link')->willThrowException(new LocalizedException(__('already linked')));
        $this->messages->expects(self::once())->method('addErrorMessage');
        $this->storage->expects(self::once())->method('clear');

        $this->observer->execute($this->event('mario@example.com'));
    }

    private function pending(?string $claimedEmail): PendingLogin
    {
        return new PendingLogin(new Profile('instagram', '555', null, false, 'mario.shop', ''), '', $claimedEmail);
    }

    private function event(string $email): Observer
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getEmail')->willReturn($email);
        $customer->method('getFirstname')->willReturn('Mario');
        $customer->method('getLastname')->willReturn('Rossi');

        return new Observer(['event' => new Event(['customer' => $customer])]);
    }
}
