<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model\ResourceModel;

use Digitalway\SocialLogin\Model\ResourceModel\Identity;
use Magento\Customer\Model\Config\Share as CustomerShareConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class IdentityTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private CustomerShareConfig&MockObject $shareConfig;
    private Select&MockObject $select;
    private Identity $identity;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->shareConfig = $this->createMock(CustomerShareConfig::class);
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();
        $this->connection->method('select')->willReturn($this->select);

        $context = $this->createMock(Context::class);

        $identity = $this->getMockBuilder(Identity::class)
            ->setConstructorArgs([$context, $this->shareConfig])
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $identity->method('getConnection')->willReturn($this->connection);
        $identity->method('getMainTable')->willReturn(Identity::TABLE);
        $this->identity = $identity;
    }

    public function testFindCustomerIdInWebsiteScopeFiltersByWebsite(): void
    {
        $this->shareConfig->method('isWebsiteScope')->willReturn(true);

        $whereCalls = [];
        $this->select->method('where')->willReturnCallback(function (string $cond, $val = null) use (&$whereCalls) {
            $whereCalls[] = [$cond, $val];
            return $this->select;
        });

        $this->connection->method('fetchOne')->with($this->select)->willReturn('7');

        $result = $this->identity->findCustomerId('google', '42', 2);

        self::assertSame(7, $result);
        self::assertContains(['website_id = ?', 2], $whereCalls);
    }

    public function testFindCustomerIdInGlobalScopeOmitsWebsiteFilter(): void
    {
        $this->shareConfig->method('isWebsiteScope')->willReturn(false);

        $whereCalls = [];
        $this->select->method('where')->willReturnCallback(function (string $cond, $val = null) use (&$whereCalls) {
            $whereCalls[] = [$cond, $val];
            return $this->select;
        });

        $this->connection->method('fetchOne')->with($this->select)->willReturn('7');

        $result = $this->identity->findCustomerId('google', '42', 2);

        self::assertSame(7, $result);
        foreach ($whereCalls as [$cond]) {
            self::assertStringNotContainsString('website_id', $cond);
        }
    }

    public function testFindCustomerIdReturnsNullWhenNotFound(): void
    {
        $this->shareConfig->method('isWebsiteScope')->willReturn(true);
        $this->connection->method('fetchOne')->with($this->select)->willReturn(false);

        self::assertNull($this->identity->findCustomerId('google', '42', 1));
    }

    public function testHasProviderInWebsiteScopeFiltersByWebsite(): void
    {
        $this->shareConfig->method('isWebsiteScope')->willReturn(true);

        $whereCalls = [];
        $this->select->method('where')->willReturnCallback(function (string $cond, $val = null) use (&$whereCalls) {
            $whereCalls[] = [$cond, $val];
            return $this->select;
        });

        $this->connection->method('fetchOne')->with($this->select)->willReturn('1');

        $result = $this->identity->hasProvider(7, 'google', 2);

        self::assertTrue($result);
        self::assertContains(['website_id = ?', 2], $whereCalls);
    }

    public function testHasProviderInGlobalScopeOmitsWebsiteFilter(): void
    {
        $this->shareConfig->method('isWebsiteScope')->willReturn(false);

        $whereCalls = [];
        $this->select->method('where')->willReturnCallback(function (string $cond, $val = null) use (&$whereCalls) {
            $whereCalls[] = [$cond, $val];
            return $this->select;
        });

        $this->connection->method('fetchOne')->with($this->select)->willReturn('1');

        $result = $this->identity->hasProvider(7, 'google', 2);

        self::assertTrue($result);
        foreach ($whereCalls as [$cond]) {
            self::assertStringNotContainsString('website_id', $cond);
        }
    }

    public function testLinkInsertsTheIdentityWhenNotYetLinked(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);
        $this->connection->expects(self::once())->method('insert')->with(Identity::TABLE, [
            'customer_id' => 7,
            'website_id' => 1,
            'provider' => 'google',
            'provider_user_id' => '42',
            'email' => 'm@x.it',
        ]);

        $this->identity->link(7, 1, 'google', '42', 'm@x.it');
    }

    public function testLinkRefusesProfileAlreadyLinkedToAnotherCustomer(): void
    {
        $this->connection->method('fetchOne')->willReturn('99');
        $this->connection->expects(self::never())->method('insert');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('This profile is already linked to an account.');

        $this->identity->link(7, 1, 'google', '42', 'm@x.it');
    }

    public function testConcurrentDuplicateBecomesLocalizedExceptionKeepingTheCause(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);
        $duplicate = new DuplicateException('Duplicate entry');
        $this->connection->method('insert')->willThrowException($duplicate);

        try {
            $this->identity->link(7, 1, 'google', '42', 'm@x.it');
            self::fail('LocalizedException expected');
        } catch (LocalizedException $e) {
            self::assertSame($duplicate, $e->getPrevious());
        }
    }

    public function testOtherDatabaseErrorsAreNotHidden(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);
        $error = new \Zend_Db_Statement_Exception('Deadlock found');
        $this->connection->method('insert')->willThrowException($error);

        $this->expectExceptionObject($error);

        $this->identity->link(7, 1, 'google', '42', 'm@x.it');
    }
}
