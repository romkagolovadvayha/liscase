<?php

use console\components\migration\Migration;

/**
 * Таблица архива статистики (прошедшие вайпы).
 * Текущий вайп пишется в statistics, старые — в statistics_archive.
 * Чтение: Statistics::modelClassForWipe() выбирает таблицу по вайпу.
 */
class m260226_120000_create_statistics_archive_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%statistics_archive}}', [
            'id'         => self::PRIMARY_KEY,
            'steam_id'   => self::VARCHAR_FIELD,
            'key'        => self::VARCHAR_FIELD,
            'value'      => self::INT_FIELD,
            'server_tag' => self::VARCHAR_FIELD,
            'wipe'       => self::VARCHAR_FIELD,
        ]);
        $this->createIndex('idx_statistics_archive_steam_server_wipe', '{{%statistics_archive}}', ['steam_id', 'server_tag', 'wipe']);
        $this->createIndex('idx_statistics_archive_server_wipe', '{{%statistics_archive}}', ['server_tag', 'wipe']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%statistics_archive}}');
    }
}
