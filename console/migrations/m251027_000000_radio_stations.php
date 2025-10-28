<?php

use console\components\migration\Migration;

/**
 * Class m251027_000000_radio_stations
 */
class m251027_000000_radio_stations extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Таблица радиостанций
        $this->createTable('radio_station', [
            'id'         => self::PRIMARY_KEY,
            'name'       => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT DEFAULT NULL',
            'port'       => 'INT(10) UNSIGNED NOT NULL',
            'folder_name' => 'VARCHAR(255) NOT NULL COMMENT "Название папки в node/mode/sounds/"',
            'status'     => self::TINYINT_FIELD . ' DEFAULT 1 COMMENT "1-Активна, 0-Неактивна"',
            'is_running' => self::TINYINT_FIELD . ' DEFAULT 0 COMMENT "1-Запущена, 0-Остановлена"',
            'current_track_id' => 'INT(10) UNSIGNED DEFAULT NULL',
            'listeners_count' => 'INT(10) UNSIGNED DEFAULT 0',
            'created_at' => self::TIMESTAMP_FIELD,
            'updated_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        // Таблица треков
        $this->createTable('radio_track', [
            'id'         => self::PRIMARY_KEY,
            'radio_station_id' => self::INT_FIELD_NOT_NULL,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'title'      => 'VARCHAR(255) NOT NULL',
            'artist'     => 'VARCHAR(255) DEFAULT NULL',
            'filename'   => 'VARCHAR(255) NOT NULL COMMENT "Название файла MP3"',
            'duration'   => 'INT(10) UNSIGNED DEFAULT NULL COMMENT "Длительность в секундах"',
            'status'     => self::TINYINT_FIELD . ' DEFAULT 3 COMMENT "1-Одобрен, 2-Отклонен, 3-На модерации"',
            'likes'      => 'INT(10) UNSIGNED DEFAULT 0',
            'plays'      => 'INT(10) UNSIGNED DEFAULT 0 COMMENT "Количество проигрываний"',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        // Таблица лайков треков
        $this->createTable('radio_track_like', [
            'id'         => self::PRIMARY_KEY,
            'radio_track_id' => self::INT_FIELD_NOT_NULL,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'type'       => self::TINYINT_FIELD . ' DEFAULT 1 COMMENT "1-Лайк"',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        // Индексы
        $this->createIndex('idx_radio_station_folder', 'radio_station', 'folder_name', true);
        $this->createIndex('idx_radio_station_port', 'radio_station', 'port', true);
        $this->createIndex('idx_radio_track_status', 'radio_track', 'status');
        $this->createIndex('idx_radio_track_station', 'radio_track', 'radio_station_id');
        $this->createIndex('idx_radio_track_user', 'radio_track', 'user_id');
        $this->createIndex('idx_radio_track_like_unique', 'radio_track_like', ['radio_track_id', 'user_id'], true);

        // Внешние ключи
        $this->addForeignKey('fk_radio_track_station', 'radio_track', 'radio_station_id',
            'radio_station', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('fk_radio_track_user', 'radio_track', 'user_id',
            'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('fk_radio_track_like_track', 'radio_track_like', 'radio_track_id',
            'radio_track', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('fk_radio_track_like_user', 'radio_track_like', 'user_id',
            'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('fk_radio_station_current_track', 'radio_station', 'current_track_id',
            'radio_track', 'id', 'SET NULL', 'CASCADE');

        // Добавляем 2 дефолтных радиостанции
        $this->insert('radio_station', [
            'name' => 'Радио #1',
            'description' => 'Первая радиостанция сервера',
            'port' => 8081,
            'folder_name' => '1',
            'status' => 1,
            'is_running' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->insert('radio_station', [
            'name' => 'Радио #2',
            'description' => 'Вторая радиостанция сервера',
            'port' => 8082,
            'folder_name' => '2',
            'status' => 1,
            'is_running' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_radio_station_current_track', 'radio_station');
        $this->dropForeignKey('fk_radio_track_like_user', 'radio_track_like');
        $this->dropForeignKey('fk_radio_track_like_track', 'radio_track_like');
        $this->dropForeignKey('fk_radio_track_user', 'radio_track');
        $this->dropForeignKey('fk_radio_track_station', 'radio_track');

        $this->dropTable('radio_track_like');
        $this->dropTable('radio_track');
        $this->dropTable('radio_station');
    }
}

