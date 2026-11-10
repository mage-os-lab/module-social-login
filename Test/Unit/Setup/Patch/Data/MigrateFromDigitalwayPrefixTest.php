<?php
declare(strict_types=1);

namespace MageOS\SocialLogin\Test\Unit\Setup\Patch\Data;

use MageOS\SocialLogin\Setup\Patch\Data\MigrateFromDigitalwayPrefix;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MigrateFromDigitalwayPrefixTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private MigrateFromDigitalwayPrefix $patch;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);

        $this->patch = new MigrateFromDigitalwayPrefix($setup);
    }

    public function testCopiesIdentitiesAndMovesConfigToNewPrefix(): void
    {
        $this->connection->method('isTableExists')->willReturn(true);
        $this->connection->method('fetchAll')->willReturn([
            ['config_id' => 5, 'scope' => 'default', 'scope_id' => 0,
             'path' => 'digitalway_sociallogin/google/client_id', 'value' => 'abc'],
        ]);
        $this->connection->expects($this->once())->method('insertFromSelect')
            ->with($this->anything(), 'mageos_social_identity', $this->anything(), AdapterInterface::INSERT_IGNORE)
            ->willReturn('INSERT ...');
        $this->connection->expects($this->once())->method('insertOnDuplicate')
            ->with('core_config_data', [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'mageos_sociallogin/google/client_id',
                'value' => 'abc',
            ], ['scope']);
        $this->connection->expects($this->once())->method('delete')
            ->with('core_config_data', ['config_id = ?' => 5]);

        $this->assertSame($this->patch, $this->patch->apply());
    }

    public function testSkipsIdentityCopyWhenLegacyTableIsMissing(): void
    {
        $this->connection->method('isTableExists')->willReturn(false);
        $this->connection->method('fetchAll')->willReturn([]);
        $this->connection->expects($this->never())->method('insertFromSelect');
        $this->connection->expects($this->never())->method('insertOnDuplicate');

        $this->patch->apply();
    }
}
