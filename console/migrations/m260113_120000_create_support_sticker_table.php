<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `support_sticker`.
 */
class m260113_120000_create_support_sticker_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('support_sticker', [
            'id' => self::PRIMARY_KEY,
            'code' => 'VARCHAR(50) NOT NULL COMMENT \'Код стикера (уникальный идентификатор)\'',
            'name' => 'VARCHAR(255) NOT NULL COMMENT \'Название стикера\'',
            'file' => 'VARCHAR(512) NOT NULL COMMENT \'Путь к файлу стикера в S3\'',
            'type' => 'VARCHAR(20) NOT NULL DEFAULT \'image\' COMMENT \'Тип стикера: image, video\'',
            'width' => 'INT(10) UNSIGNED DEFAULT NULL COMMENT \'Ширина стикера в пикселях\'',
            'height' => 'INT(10) UNSIGNED DEFAULT NULL COMMENT \'Высота стикера в пикселях\'',
            'sort' => 'INT(10) DEFAULT 0 COMMENT \'Порядок сортировки\'',
            'status' => 'TINYINT(1) DEFAULT 1 COMMENT \'Статус (0-неактивен, 1-активен)\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-support_sticker-code', 'support_sticker', 'code', true);
        $this->createIndex('idx-support_sticker-status', 'support_sticker', 'status');
        $this->createIndex('idx-support_sticker-sort', 'support_sticker', 'sort');
        $this->createIndex('idx-support_sticker-created_at', 'support_sticker', 'created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('support_sticker');
    }
}










