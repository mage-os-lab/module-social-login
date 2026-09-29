<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\CustomerCreator;
use Digitalway\SocialLogin\Model\Profile;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Math\Random;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class CustomerCreatorTest extends TestCase
{
    private CustomerInterface&MockObject $customer;
    private CustomerRepositoryInterface&MockObject $repository;
    private AccountManagementInterface&MockObject $accountManagement;
    private EmailNotificationInterface&MockObject $emailNotification;
    private LoggerInterface&MockObject $logger;
    private CustomerCreator $creator;

    protected function setUp(): void
    {
        $this->customer = $this->createMock(CustomerInterface::class);
        $factory = $this->createMock(CustomerInterfaceFactory::class);
        $factory->method('create')->willReturn($this->customer);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('getHash')->willReturn('HASH');
        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->willReturn('random-password');

        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);
        $this->emailNotification = $this->createMock(EmailNotificationInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->creator = new CustomerCreator(
            $factory,
            $this->repository,
            $this->accountManagement,
            $this->emailNotification,
            $storeManager,
            $encryptor,
            $random,
            $this->logger
        );
    }

    public function testCreateVerifiedSavesWithRandomPasswordHashAndSendsWelcomeEmail(): void
    {
        $this->expectCustomerData();
        $saved = $this->savedCustomer();
        $this->repository->expects(self::once())->method('save')->with($this->customer, 'HASH')->willReturn($saved);
        $this->emailNotification->expects(self::once())->method('newAccount')
            ->with($saved, EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED_NO_PASSWORD, '', 1);
        $this->accountManagement->expects(self::never())->method('createAccount');

        self::assertSame($saved, $this->creator->createVerified($this->profile()));
    }

    public function testCreateVerifiedSurvivesWelcomeEmailFailure(): void
    {
        $saved = $this->savedCustomer();
        $this->repository->method('save')->willReturn($saved);
        $this->emailNotification->method('newAccount')->willThrowException(new MailException(__('smtp down')));
        $this->logger->expects(self::once())->method('warning');

        self::assertSame($saved, $this->creator->createVerified($this->profile()));
    }

    public function testCreateUnverifiedDelegatesToAccountManagement(): void
    {
        $this->expectCustomerData();
        $saved = $this->savedCustomer();
        $this->accountManagement->expects(self::once())->method('createAccount')->with($this->customer)->willReturn($saved);
        $this->repository->expects(self::never())->method('save');

        self::assertSame($saved, $this->creator->createUnverified($this->profile()));
    }

    private function profile(): Profile
    {
        return new Profile('google', '42', 'm@x.it', true, 'Mario', 'Rossi');
    }

    private function savedCustomer(): CustomerInterface&MockObject
    {
        $saved = $this->createMock(CustomerInterface::class);
        $saved->method('getId')->willReturn(10);
        $saved->method('getStoreId')->willReturn(1);

        return $saved;
    }

    private function expectCustomerData(): void
    {
        $this->customer->expects(self::once())->method('setWebsiteId')->with(2);
        $this->customer->expects(self::once())->method('setStoreId')->with(1);
        $this->customer->expects(self::once())->method('setEmail')->with('m@x.it');
        $this->customer->expects(self::once())->method('setFirstname')->with('Mario');
        $this->customer->expects(self::once())->method('setLastname')->with('Rossi');
    }
}
