<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Controller\Account;

use Digitalway\SocialLogin\Controller\Account\CompleteEmailPost;
use Digitalway\SocialLogin\Model\CustomerCreator;
use Digitalway\SocialLogin\Model\CustomerLinker;
use Digitalway\SocialLogin\Model\LoginHandler;
use Digitalway\SocialLogin\Model\PendingLogin;
use Digitalway\SocialLogin\Model\PendingProfileStorage;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\ProfileLoginFlow;
use Digitalway\SocialLogin\Model\ProviderPool;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class CompleteEmailPostTest extends TestCase
{
    private const FORM_ROUTE = 'sociallogin/account/completeemail';

    private PendingProfileStorage&MockObject $pendingStorage;
    private CustomerRepositoryInterface&MockObject $repository;
    private CustomerCreator&MockObject $creator;
    private CustomerLinker&MockObject $linker;
    private ManagerInterface&MockObject $messageManager;
    private LoggerInterface&MockObject $logger;
    private Redirect&MockObject $redirect;
    private CompleteEmailPost $controller;

    protected function setUp(): void
    {
        $params = ['email' => 'new@x.it', 'firstname' => 'Mario', 'lastname' => 'Rossi'];
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);

        $this->pendingStorage = $this->createMock(PendingProfileStorage::class);
        $this->pendingStorage->method('get')->willReturn(
            new PendingLogin(new Profile('instagram', '555', null, false, 'mario.shop', ''), '', null)
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->creator = $this->createMock(CustomerCreator::class);
        $this->linker = $this->createMock(CustomerLinker::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
        $this->redirect->method('setUrl')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $this->controller = new CompleteEmailPost(
            $request,
            $this->pendingStorage,
            $this->repository,
            $storeManager,
            $this->creator,
            $this->linker,
            $this->createMock(AccountManagementInterface::class),
            $this->createMock(LoginHandler::class),
            $this->createMock(ProfileLoginFlow::class),
            $this->createMock(ProviderPool::class),
            $redirectFactory,
            $this->messageManager,
            $this->logger
        );
    }

    public function testDatabaseErrorWhileLinkingShowsGenericMessageInsteadOfErrorPage(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(9);
        $this->repository->method('get')->willThrowException(new NoSuchEntityException());
        $this->creator->method('createUnverified')->willReturn($customer);
        $this->linker->method('link')->willThrowException(new \Zend_Db_Statement_Exception('Deadlock found'));

        $this->pendingStorage->expects(self::never())->method('clear');
        $this->messageManager->expects(self::once())->method('addErrorMessage')
            ->with(__('Could not complete sign-in with %1. Please try again.', 'Instagram'));
        $this->logger->expects(self::once())->method('error');
        $this->redirect->expects(self::once())->method('setPath')->with(self::FORM_ROUTE);

        self::assertSame($this->redirect, $this->controller->execute());
    }

    public function testDatabaseErrorWhileLookingUpTheEmailShowsGenericMessage(): void
    {
        $this->repository->method('get')->willThrowException(new \Zend_Db_Adapter_Exception('Connection refused'));

        $this->creator->expects(self::never())->method('createUnverified');
        $this->logger->expects(self::once())->method('error');
        $this->redirect->expects(self::once())->method('setPath')->with(self::FORM_ROUTE);

        self::assertSame($this->redirect, $this->controller->execute());
    }
}
