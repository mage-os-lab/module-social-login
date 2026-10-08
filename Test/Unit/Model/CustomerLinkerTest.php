<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\CustomerCreator;
use Digitalway\SocialLogin\Model\CustomerLinker;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\ResourceModel\Identity;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CustomerLinkerTest extends TestCase
{
    private const WEBSITE_ID = 1;

    private Identity&MockObject $identity;
    private CustomerRepositoryInterface&MockObject $repository;
    private CustomerCreator&MockObject $creator;
    private CustomerLinker $linker;

    protected function setUp(): void
    {
        $this->identity = $this->createMock(Identity::class);
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->creator = $this->createMock(CustomerCreator::class);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->linker = new CustomerLinker($this->identity, $this->repository, $this->creator, $storeManager);
    }

    public function testExistingIdentityLogsInLinkedCustomer(): void
    {
        $customer = $this->customer(5);
        $this->identity->method('findCustomerId')->with('google', '42', self::WEBSITE_ID)->willReturn(5);
        $this->repository->method('getById')->with(5)->willReturn($customer);
        $this->identity->expects(self::never())->method('link');
        $this->creator->expects(self::never())->method('createVerified');

        $result = $this->linker->resolve($this->profile('m@x.it', true));

        self::assertFalse($result->isPending());
        self::assertSame($customer, $result->customer);
    }

    public function testVerifiedEmailOfExistingCustomerIsLinked(): void
    {
        $customer = $this->customer(7);
        $this->identity->method('findCustomerId')->willReturn(null);
        $this->repository->method('get')->with('m@x.it', self::WEBSITE_ID)->willReturn($customer);
        $this->identity->method('hasProvider')->willReturn(false);
        $this->identity->expects(self::once())->method('link')->with(7, self::WEBSITE_ID, 'google', '42', 'm@x.it');

        $result = $this->linker->resolve($this->profile('m@x.it', true));

        self::assertSame($customer, $result->customer);
    }

    public function testVerifiedEmailWithoutCustomerCreatesAndLinks(): void
    {
        $customer = $this->customer(9);
        $profile = $this->profile('new@x.it', true);
        $this->identity->method('findCustomerId')->willReturn(null);
        $this->repository->method('get')->willThrowException(new NoSuchEntityException());
        $this->creator->expects(self::once())->method('createVerified')->with($profile)->willReturn($customer);
        $this->identity->method('hasProvider')->willReturn(false);
        $this->identity->expects(self::once())->method('link')->with(9, self::WEBSITE_ID, 'google', '42', 'new@x.it');

        self::assertSame($customer, $this->linker->resolve($profile)->customer);
    }

    public function testMissingEmailGoesPending(): void
    {
        $this->identity->method('findCustomerId')->willReturn(null);
        $this->repository->expects(self::never())->method('get');

        self::assertTrue($this->linker->resolve($this->profile(null, false))->isPending());
    }

    public function testUnverifiedEmailNeverAutoLinksAndGoesPending(): void
    {
        $this->identity->method('findCustomerId')->willReturn(null);
        $this->repository->expects(self::never())->method('get');
        $this->identity->expects(self::never())->method('link');

        self::assertTrue($this->linker->resolve($this->profile('m@x.it', false))->isPending());
    }

    public function testVerifiedEmailWithoutNamesAndNoCustomerGoesPending(): void
    {
        $this->identity->method('findCustomerId')->willReturn(null);
        $this->repository->method('get')->willThrowException(new NoSuchEntityException());
        $this->creator->expects(self::never())->method('createVerified');

        $profile = new Profile('google', '42', 'cher@x.it', true, 'Cher', '');

        self::assertTrue($this->linker->resolve($profile)->isPending());
    }

    public function testLinkRefusesSecondAccountOfSameProvider(): void
    {
        $this->identity->method('hasProvider')->with(7, 'google', self::WEBSITE_ID)->willReturn(true);
        $this->identity->expects(self::never())->method('link');

        $this->expectException(LocalizedException::class);
        $this->linker->link(7, $this->profile('m@x.it', true));
    }

    private function profile(?string $email, bool $verified): Profile
    {
        return new Profile('google', '42', $email, $verified, 'Mario', 'Rossi');
    }

    private function customer(int $id): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn($id);

        return $customer;
    }
}
