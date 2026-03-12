<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `market_skins`.
 */
class m260115_120000_create_market_skins_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('market_skins', [
            'id' => self::PRIMARY_KEY,
            'class_id' => 'BIGINT(20) UNSIGNED NOT NULL COMMENT \'ID класса предмета из rust.tm\'',
            'instance_id' => 'BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'ID инстанса предмета из rust.tm\'',
            'market_hash_name' => 'VARCHAR(255) NOT NULL COMMENT \'Полное название предмета для API rust.tm\'',
            'name' => 'VARCHAR(255) NOT NULL COMMENT \'Короткое название предмета\'',
            'ru_name' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Русское название предмета\'',
            'category' => 'VARCHAR(100) DEFAULT NULL COMMENT \'Категория предмета (Weapon, Armor, etc.)\'',
            'ru_quality' => 'VARCHAR(50) DEFAULT NULL COMMENT \'Качество предмета на русском\'',
            'text_color' => 'VARCHAR(10) DEFAULT NULL COMMENT \'Цвет текста в hex\'',
            'bg_color' => 'VARCHAR(10) DEFAULT NULL COMMENT \'Цвет фона в hex\'',
            'price' => 'INT(11) UNSIGNED NOT NULL COMMENT \'Цена с rust.tm (в копейках)\'',
            'our_price' => 'INT(11) UNSIGNED NOT NULL COMMENT \'Наша цена с накруткой (в копейках)\'',
            'markup_percent' => 'DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 30.00 COMMENT \'Процент накрутки\'',
            'avg_price' => 'INT(11) UNSIGNED DEFAULT NULL COMMENT \'Средняя цена (в копейках)\'',
            'popularity_7d' => 'INT(11) DEFAULT 0 COMMENT \'Популярность за 7 дней\'',
            'image_url' => 'VARCHAR(500) DEFAULT NULL COMMENT \'URL изображения 100px\'',
            'image300_url' => 'VARCHAR(500) DEFAULT NULL COMMENT \'URL изображения 300px\'',
            'status' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT \'1-активен, 0-неактивен\'',
            'is_stat_trak' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'Является ли StatTrak предметом\'',
            'last_synced_at' => self::TIMESTAMP_FIELD . ' COMMENT \'Время последней синхронизации с rust.tm\'',
            'created_at' => self::TIMESTAMP_FIELD . ' COMMENT \'Время создания записи\'',
            'updated_at' => self::TIMESTAMP_FIELD . ' COMMENT \'Время последнего обновления\'',
        ], self::TABLE_OPTIONS_MB4);

        // Уникальный индекс для class_id + instance_id
        $this->createIndex('idx-market_skins-unique_class_instance', 'market_skins', ['class_id', 'instance_id'], true);

        // Индексы для быстрого поиска и фильтрации
        $this->createIndex('idx-market_skins-market_hash_name', 'market_skins', 'market_hash_name');
        $this->createIndex('idx-market_skins-category', 'market_skins', 'category');
        $this->createIndex('idx-market_skins-status', 'market_skins', 'status');
        $this->createIndex('idx-market_skins-our_price', 'market_skins', 'our_price');
        $this->createIndex('idx-market_skins-updated_at', 'market_skins', 'updated_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('market_skins');
    }
}

