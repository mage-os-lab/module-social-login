<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\CustomerLinker;
use Digitalway\SocialLogin\Model\LinkResult;
use Digitalway\SocialLogin\Model\LoginHandler;
use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\ProfileLoginFlow;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ProfileLoginFlowTest extends TestCase
{
    private CustomerLinker&MockObject $linker;
    private LoginHandler&MockObject $loginHandler;
    private PendingProfileStorage&MockObject $storage;
    private ProfileLoginFlow $flow;

    protected function setUp(): void
    {
        $this->linker = $this->createMock(CustomerLinker::class);
        $this->loginHandler = $this->createMock(LoginHandler::class);
        $this->storage = $this->createMock(PendingProfileStorage::class);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn (string $route): string => 'https://shop.test/' . $route . '/');

        $this->flow = new ProfileLoginFlow($this->linker, $this->loginHandler, $this->storage, $url);
    }

    public function testPendingProfileIsStoredAndUserSentToCompletionForm(): void
    {
        $profile = new Profile('instagram', '555', null, false, 'mario', '');
        $this->linker->method('resolve')->willReturn(LinkResult::pending());
        $this->storage->expects(self::once())->method('save')->with($profile, 'https://shop.test/checkout/');
        $this->loginHandler->expects(self::never())->method('login');

        self::assertSame(
            'https://shop.test/sociallogin/account/completeemail/',
            $this->flow->complete($profile, 'https://shop.test/checkout/')
        );
    }

    public function testResolvedCustomerIsLoggedInAndSentToReturnUrl(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->linker->method('resolve')->willReturn(LinkResult::loggedIn($customer));
        $this->loginHandler->expects(self::once())->method('login')->with($customer);
        $this->storage->expects(self::never())->method('save');

        self::assertSame(
            'https://shop.test/checkout/',
            $this->flow->complete(new Profile('google', '1', 'a@b.it', true, 'A', 'B'), 'https://shop.test/checkout/')
        );
    }

    public function testWithoutReturnUrlGoesToAccountDashboard(): void
    {
        $this->linker->method('resolve')->willReturn(LinkResult::loggedIn($this->createMock(CustomerInterface::class)));

        self::assertSame(
            'https://shop.test/customer/account/',
            $this->flow->complete(new Profile('google', '1', 'a@b.it', true, 'A', 'B'), '')
        );
    }
}
