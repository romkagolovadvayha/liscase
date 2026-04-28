<?php

use common\models\user\UserRaidOwner;
use console\components\migration\Migration;
use yii\db\Query;

/**
 * Таблица user_raid_owner: нормализованные Steam ID для индексных выборок вместо LIKE по JSON owners.
 * Колонка user_raid.owners сохраняется (JSON для API); строки синхронизируются из неё при миграции и при сохранении рейда.
 */
class m260428_143000_user_raid_owner extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%user_raid_owner}}', true) !== null) {
            $this->ensureUserRaidDedupIndex();

            return;
        }

        $this->createTable(
            '{{%user_raid_owner}}',
            [
                'id' => $this->bigPrimaryKey(),
                'user_raid_id' => $this->integer()->unsigned()->notNull()->comment('FK user_raid.id'),
                'steam_id' => $this->string(32)->notNull()->comment('Steam64, только цифры'),
            ],
            static::TABLE_OPTIONS_MB4
        );

        $this->createIndex(
            'uq_user_raid_owner_raid_steam',
            '{{%user_raid_owner}}',
            ['user_raid_id', 'steam_id'],
            true
        );
        $this->createIndex('idx_user_raid_owner_steam', '{{%user_raid_owner}}', 'steam_id');

        $this->addForeignKey(
            'fk_user_raid_owner_raid',
            '{{%user_raid_owner}}',
            'user_raid_id',
            '{{%user_raid}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->backfillFromOwnersColumn();

        $this->ensureUserRaidDedupIndex();
    }

    public function safeDown()
    {
        $this->dropIndexIfExists('idx_user_raid_notify_loc_created', '{{%user_raid}}');

        $schema = $this->db->schema->getTableSchema('{{%user_raid_owner}}', true);
        if ($schema !== null) {
            $this->dropForeignKeyIfExists('fk_user_raid_owner_raid', '{{%user_raid_owner}}');
            $this->dropTable('{{%user_raid_owner}}');
        }
    }

    private function backfillFromOwnersColumn(): void
    {
        $lastId = 0;
        $acc = [];
        while (true) {
            $rows = (new Query())
                ->from('{{%user_raid}}')
                ->select(['id', 'owners'])
                ->where(['>', 'id', $lastId])
                ->orderBy(['id' => SORT_ASC])
                ->limit(500)
                ->all($this->db);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $rid = (int)($row['id'] ?? 0);
                if ($rid > $lastId) {
                    $lastId = $rid;
                }
                $ownersRaw = $row['owners'] ?? null;
                $steamIds = UserRaidOwner::steamIdsFromOwnersColumn(is_string($ownersRaw) ? $ownersRaw : null);
                foreach ($steamIds as $sid) {
                    $acc[] = [$rid, $sid];
                    if (count($acc) >= 1500) {
                        $this->batchInsertSafe($acc);
                        $acc = [];
                    }
                }
            }
        }

        if ($acc !== []) {
            $this->batchInsertSafe($acc);
        }
    }

    /**
     * @param array<int,array{0:int,1:string}> $pairs
     */
    private function batchInsertSafe(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }
        foreach (array_chunk($pairs, 500) as $chunk) {
            $this->batchInsert('{{%user_raid_owner}}', ['user_raid_id', 'steam_id'], $chunk);
        }
    }

    private function ensureUserRaidDedupIndex(): void
    {
        $this->createIndexIfNotExists(
            'idx_user_raid_notify_loc_created',
            '{{%user_raid}}',
            ['notify', 'location', 'created_at']
        );
    }

    protected function dropForeignKeyIfExists(string $name, string $tableExpr): void
    {
        $physical = $this->physicalTableName($tableExpr);
        try {
            $row = $this->db->createCommand(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_TYPE = :fk AND CONSTRAINT_NAME = :n',
                [':t' => $physical, ':fk' => 'FOREIGN KEY', ':n' => $name]
            )->queryOne();
            if (!empty($row)) {
                $this->dropForeignKey($name, $tableExpr);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param string $tableExpr например {{%user_raid}}
     */
    protected function createIndexIfNotExists(string $name, string $tableExpr, $columns, bool $unique = false): void
    {
        $physical = $this->physicalTableName($tableExpr);
        try {
            $exists = $this->db->createCommand(
                'SHOW INDEX FROM ' . $this->db->quoteTableName($physical) . ' WHERE Key_name = :n',
                [':n' => $name]
            )->queryOne();
            if (!$exists) {
                $this->createIndex($name, $tableExpr, $columns, $unique);
            }
        } catch (\Throwable $e) {
        }
    }

    protected function dropIndexIfExists(string $name, string $tableExpr): void
    {
        $physical = $this->physicalTableName($tableExpr);
        try {
            $exists = $this->db->createCommand(
                'SHOW INDEX FROM ' . $this->db->quoteTableName($physical) . ' WHERE Key_name = :n',
                [':n' => $name]
            )->queryOne();
            if ($exists) {
                $this->dropIndex($name, $tableExpr);
            }
        } catch (\Throwable $e) {
        }
    }

    private function physicalTableName(string $tableExpr): string
    {
        return $this->db->getSchema()->getRawTableName($tableExpr);
    }
}
