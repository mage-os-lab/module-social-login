<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model\ResourceModel;

use Digitalway\SocialLogin\Model\ResourceModel\Identity;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class IdentityTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private Identity $identity;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $identity = $this->createPartialMock(Identity::class, ['getConnection', 'getMainTable']);
        $identity->method('getConnection')->willReturn($this->connection);
        $identity->method('getMainTable')->willReturn(Identity::TABLE);
        $this->identity = $identity;
    }

    public function testLinkInsertsTheIdentity(): void
    {
        $this->connection->expects(self::once())->method('insert')->with(Identity::TABLE, [
            'customer_id' => 7,
            'website_id' => 1,
            'provider' => 'google',
            'provider_user_id' => '42',
            'email' => 'm@x.it',
        ]);

        $this->identity->link(7, 1, 'google', '42', 'm@x.it');
    }

    public function testConcurrentDuplicateBecomesLocalizedExceptionKeepingTheCause(): void
    {
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
        $error = new \Zend_Db_Statement_Exception('Deadlock found');
        $this->connection->method('insert')->willThrowException($error);

        $this->expectExceptionObject($error);

        $this->identity->link(7, 1, 'google', '42', 'm@x.it');
    }
}
