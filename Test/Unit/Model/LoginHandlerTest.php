<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\LoginHandler;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\CookieMetadata;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class LoginHandlerTest extends TestCase
{
    private CustomerSession&MockObject $session;
    private AccountManagementInterface&MockObject $accountManagement;
    private CookieManagerInterface&MockObject $cookieManager;
    private CookieMetadata&MockObject $metadata;
    private LoginHandler $handler;

    protected function setUp(): void
    {
        $this->session = $this->createMock(CustomerSession::class);
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);
        $this->cookieManager = $this->createMock(CookieManagerInterface::class);
        $this->metadata = $this->createMock(CookieMetadata::class);
        $this->metadata->method('setPath')->willReturnSelf();
        $metadataFactory = $this->createMock(CookieMetadataFactory::class);
        $metadataFactory->method('createCookieMetadata')->willReturn($this->metadata);

        $this->handler = new LoginHandler($this->session, $this->accountManagement, $this->cookieManager, $metadataFactory);
    }

    public function testLoginSetsSessionAndInvalidatesCustomerDataCookie(): void
    {
        $customer = $this->customer();
        $this->accountManagement->method('getConfirmationStatus')->with(3)
            ->willReturn(AccountManagementInterface::ACCOUNT_CONFIRMED);
        $this->session->expects(self::once())->method('setCustomerDataAsLoggedIn')->with($customer);
        $this->cookieManager->method('getCookie')->with('mage-cache-sessid')->willReturn('1');
        $this->cookieManager->expects(self::once())->method('deleteCookie')->with('mage-cache-sessid', $this->metadata);

        $this->handler->login($customer);
    }

    public function testUnconfirmedAccountIsNotLoggedIn(): void
    {
        $this->accountManagement->method('getConfirmationStatus')
            ->willReturn(AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED);
        $this->session->expects(self::never())->method('setCustomerDataAsLoggedIn');

        $this->expectException(LocalizedException::class);
        $this->handler->login($this->customer());
    }

    private function customer(): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(3);

        return $customer;
    }
}
