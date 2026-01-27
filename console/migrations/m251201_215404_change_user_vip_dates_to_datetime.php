<?php

use console\components\migration\Migration;

/**
 * Class m251201_215404_change_user_vip_dates_to_datetime
 * 
 * Изменяет формат дат в таблице user_vip с timestamp (INT) на DATETIME
 */
class m251201_215404_change_user_vip_dates_to_datetime extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Проверяем, существует ли таблица
        $tableSchema = $this->db->schema->getTableSchema('user_vip', true);
        if ($tableSchema === null) {
            echo "Таблица user_vip не существует. Пропускаем миграцию.\n";
            return;
        }

        // Получаем все записи для конвертации
        $records = $this->db->createCommand('SELECT id, expires_at, created_at, updated_at FROM user_vip')->queryAll();

        // Удаляем индексы, которые используют expires_at
        $this->dropIndex('idx_user_vip_user_expires', 'user_vip');
        $this->dropIndex('idx_user_vip_expires_at', 'user_vip');

        // Изменяем тип колонок на DATETIME
        $this->alterColumn('user_vip', 'expires_at', 'DATETIME NOT NULL COMMENT \'Дата окончания VIP\'');
        $this->alterColumn('user_vip', 'created_at', self::TIMESTAMP_FIELD);
        $this->alterColumn('user_vip', 'updated_at', self::TIMESTAMP_FIELD);

        // Конвертируем существующие данные из timestamp в DATETIME
        foreach ($records as $record) {
            $id = $record['id'];
            $expiresAt = !empty($record['expires_at']) ? date('Y-m-d H:i:s', $record['expires_at']) : date('Y-m-d H:i:s');
            $createdAt = !empty($record['created_at']) ? date('Y-m-d H:i:s', $record['created_at']) : date('Y-m-d H:i:s');
            $updatedAt = !empty($record['updated_at']) ? date('Y-m-d H:i:s', $record['updated_at']) : date('Y-m-d H:i:s');

            $this->update('user_vip', [
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ], ['id' => $id]);
        }

        // Восстанавливаем индексы
        $this->createIndex('idx_user_vip_expires_at', 'user_vip', 'expires_at');
        $this->createIndex('idx_user_vip_user_expires', 'user_vip', ['user_id', 'expires_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Проверяем, существует ли таблица
        $tableSchema = $this->db->schema->getTableSchema('user_vip', true);
        if ($tableSchema === null) {
            echo "Таблица user_vip не существует. Пропускаем откат миграции.\n";
            return;
        }

        // Получаем все записи для конвертации обратно
        $records = $this->db->createCommand('SELECT id, expires_at, created_at, updated_at FROM user_vip')->queryAll();

        // Удаляем индексы
        $this->dropIndex('idx_user_vip_user_expires', 'user_vip');
        $this->dropIndex('idx_user_vip_expires_at', 'user_vip');

        // Изменяем тип колонок обратно на INT
        $this->alterColumn('user_vip', 'expires_at', 'INT(10) UNSIGNED NOT NULL COMMENT \'Дата окончания VIP (timestamp)\'');
        $this->alterColumn('user_vip', 'created_at', 'INT(10) UNSIGNED NOT NULL');
        $this->alterColumn('user_vip', 'updated_at', 'INT(10) UNSIGNED NOT NULL');

        // Конвертируем данные обратно в timestamp
        foreach ($records as $record) {
            $id = $record['id'];
            $expiresAt = !empty($record['expires_at']) ? strtotime($record['expires_at']) : time();
            $createdAt = !empty($record['created_at']) ? strtotime($record['created_at']) : time();
            $updatedAt = !empty($record['updated_at']) ? strtotime($record['updated_at']) : time();

            $this->update('user_vip', [
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ], ['id' => $id]);
        }

        // Восстанавливаем индексы
        $this->createIndex('idx_user_vip_expires_at', 'user_vip', 'expires_at');
        $this->createIndex('idx_user_vip_user_expires', 'user_vip', ['user_id', 'expires_at']);
    }
}
