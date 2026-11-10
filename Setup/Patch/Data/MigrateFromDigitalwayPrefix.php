<?php
declare(strict_types=1);

namespace MageOS\SocialLogin\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Migrates data of installs created before the `digitalway` -> `mageos` rename:
 * the linked social identities and the saved configuration values.
 *
 * Nothing is dropped from the old table; once verified it can be removed manually.
 */
class MigrateFromDigitalwayPrefix implements DataPatchInterface
{
    private const OLD_TABLE = 'digitalway_social_identity';
    private const NEW_TABLE = 'mageos_social_identity';
    private const OLD_CONFIG_PREFIX = 'digitalway_sociallogin/';
    private const NEW_CONFIG_PREFIX = 'mageos_sociallogin/';

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $this->migrateIdentities();
        $this->migrateConfig();

        $connection->endSetup();

        return $this;
    }

    private function migrateIdentities(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $oldTable = $this->moduleDataSetup->getTable(self::OLD_TABLE);
        $newTable = $this->moduleDataSetup->getTable(self::NEW_TABLE);

        if (!$connection->isTableExists($oldTable) || !$connection->isTableExists($newTable)) {
            return;
        }

        $columns = ['identity_id', 'customer_id', 'website_id', 'provider', 'provider_user_id', 'email', 'created_at'];
        $select = $connection->select()->from($oldTable, $columns);
        $connection->query($connection->insertFromSelect(
            $select,
            $newTable,
            $columns,
            AdapterInterface::INSERT_IGNORE
        ));
    }

    private function migrateConfig(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $select = $connection->select()->from($table)
            ->where('path LIKE ?', self::OLD_CONFIG_PREFIX . '%');
        $rows = $connection->fetchAll($select);

        foreach ($rows as $row) {
            $newPath = self::NEW_CONFIG_PREFIX . substr($row['path'], strlen(self::OLD_CONFIG_PREFIX));
            // On a duplicate only `scope` is "updated" (to itself): a value already saved under
            // the new path wins over the legacy one.
            $connection->insertOnDuplicate($table, [
                'scope' => $row['scope'],
                'scope_id' => $row['scope_id'],
                'path' => $newPath,
                'value' => $row['value'],
            ], ['scope']);
            $connection->delete($table, ['config_id = ?' => $row['config_id']]);
        }
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
