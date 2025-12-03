<?php

use yii\db\Migration;

/**
 * Class m251202_232851_add_year_review_indexes
 */
class m251202_232851_add_year_review_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Индексы для таблицы statistics
        // Составной индекс для запросов WHERE steam_id = X AND key = Y
        $this->createIndexIfNotExists(
            'idx_statistics_steam_id_key',
            'statistics',
            ['steam_id', 'key']
        );
        
        // Составной индекс для запросов COUNT(DISTINCT wipe) WHERE steam_id = X
        $this->createIndexIfNotExists(
            'idx_statistics_steam_id_wipe',
            'statistics',
            ['steam_id', 'wipe']
        );
        
        // Индекс для таблицы servers_reports
        // Для запросов WHERE steam_id = X
        $this->createIndexIfNotExists(
            'idx_servers_reports_steam_id',
            'servers_reports',
            'steam_id'
        );
        
        // Составной индекс для запросов WHERE steam_id = X AND recepient_steam_id
        $this->createIndexIfNotExists(
            'idx_servers_reports_steam_recepient',
            'servers_reports',
            ['steam_id', 'recepient_steam_id']
        );
        
        // Индекс для таблицы bans
        // Для запросов WHERE steam_id IN (...)
        $this->createIndexIfNotExists(
            'idx_bans_steam_id',
            'bans',
            'steam_id'
        );
        
        // Составной индекс для активных банов
        $this->createIndexIfNotExists(
            'idx_bans_steam_id_banned',
            'bans',
            ['steam_id', 'banned_at', 'unbanned_at']
        );
        
        // Индекс для таблицы skindrops
        // Для запросов WHERE steam_id = X
        $this->createIndexIfNotExists(
            'idx_skindrops_steam_id',
            'skindrops',
            'steam_id'
        );
        
        // Индекс для таблицы user_raid
        // Для запросов WHERE user_id = X AND type = Y
        $this->createIndexIfNotExists(
            'idx_user_raid_user_id_type',
            'user_raid',
            ['user_id', 'type']
        );
        
        // Индексы для таблицы statistics_kills
        // Для запросов WHERE steam_id = X AND type = Y
        $this->createIndexIfNotExists(
            'idx_statistics_kills_steam_id_type',
            'statistics_kills',
            ['steam_id', 'type']
        );
        
        // Составной индекс для запросов с distance
        $this->createIndexIfNotExists(
            'idx_statistics_kills_steam_type_distance',
            'statistics_kills',
            ['steam_id', 'type', 'distance']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndexIfExists('idx_statistics_kills_steam_type_distance', 'statistics_kills');
        $this->dropIndexIfExists('idx_statistics_kills_steam_id_type', 'statistics_kills');
        $this->dropIndexIfExists('idx_user_raid_user_id_type', 'user_raid');
        $this->dropIndexIfExists('idx_skindrops_steam_id', 'skindrops');
        $this->dropIndexIfExists('idx_bans_steam_id_banned', 'bans');
        $this->dropIndexIfExists('idx_bans_steam_id', 'bans');
        $this->dropIndexIfExists('idx_servers_reports_steam_recepient', 'servers_reports');
        $this->dropIndexIfExists('idx_servers_reports_steam_id', 'servers_reports');
        $this->dropIndexIfExists('idx_statistics_steam_id_wipe', 'statistics');
        $this->dropIndexIfExists('idx_statistics_steam_id_key', 'statistics');
    }
    
    /**
     * Создает индекс, если он еще не существует
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
     * @param string|array $columns Колонки для индекса
     * @param bool $unique Уникальный индекс
     */
    protected function createIndexIfNotExists($name, $table, $columns, $unique = false)
    {
        try {
            /** @var \yii\db\Command $command */
            $command = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            $indexExists = $command->queryOne();
            if (!$indexExists) {
                $this->createIndex($name, $table, $columns, $unique);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки, если индекс уже существует
        }
    }
    
    /**
     * Удаляет индекс, если он существует
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
     */
    protected function dropIndexIfExists($name, $table)
    {
        try {
            /** @var \yii\db\Command $command */
            $command = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            $indexExists = $command->queryOne();
            if ($indexExists) {
                $this->dropIndex($name, $table);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251202_232851_add_year_review_indexes cannot be reverted.\n";

        return false;
    }
    */
}
