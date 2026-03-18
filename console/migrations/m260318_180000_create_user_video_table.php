<?php

use console\components\migration\Migration;

/**
 * Таблица видео пользователей (модерация как server_skin).
 * Идемпотентно: если таблица уже есть (после неудачного FK), только правит user_id и добавляет FK.
 */
class m260318_180000_create_user_video_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%user_video}}', true);

        if ($schema === null) {
            $this->createTable('{{%user_video}}', [
                'id' => self::PRIMARY_KEY,
                'user_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Пользователь\'',
                'name' => 'VARCHAR(255) NOT NULL COMMENT \'Название\'',
                'type' => 'VARCHAR(32) NOT NULL DEFAULT \'youtube\' COMMENT \'Тип: youtube, tiktok, other\'',
                'video_link' => 'VARCHAR(500) NOT NULL COMMENT \'Ссылка на видео\'',
                'poster_image' => 'VARCHAR(500) DEFAULT NULL COMMENT \'Постер\'',
                'poster_image_150' => 'VARCHAR(500) DEFAULT NULL COMMENT \'Постер 150px\'',
                'poster_image_400' => 'VARCHAR(500) DEFAULT NULL COMMENT \'Постер 400px\'',
                'status' => 'TINYINT(1) NOT NULL DEFAULT 3 COMMENT \'1=активен, 2=отклонен, 3=на модерации\'',
                'created_at' => 'DATETIME DEFAULT NULL',
                'updated_at' => 'DATETIME DEFAULT NULL',
            ], self::TABLE_OPTIONS_MB4);

            $this->createIndex('idx_user_video_user_id', '{{%user_video}}', 'user_id');
            $this->createIndex('idx_user_video_status', '{{%user_video}}', 'status');
            $this->addForeignKey('fk_user_video_user', '{{%user_video}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
            return;
        }

        // Таблица уже есть (осталась после ошибки FK): приводим user_id к INT UNSIGNED и добавляем FK
        $this->db->createCommand('ALTER TABLE {{%user_video}} MODIFY COLUMN [[user_id]] INT(10) UNSIGNED NOT NULL COMMENT \'Пользователь\'')->execute();
        $this->addForeignKey('fk_user_video_user', '{{%user_video}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_user_video_user', '{{%user_video}}');
        $this->dropTable('{{%user_video}}');
    }
}
