<?php

use console\components\migration\Migration;

/**
 * Лайки для видео: колонка likes в user_video, таблица user_video_like.
 */
class m260318_190000_add_user_video_likes extends Migration
{
    public function safeUp()
    {
        $table = '{{%user_video}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema && !isset($schema->columns['likes'])) {
            $this->addColumn($table, 'likes', 'INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'Количество лайков\'');
        }

        $likeTable = '{{%user_video_like}}';
        if ($this->db->schema->getTableSchema($likeTable, true) === null) {
            $this->createTable($likeTable, [
                'id' => self::PRIMARY_KEY,
                'user_video_id' => self::INT_FIELD_NOT_NULL,
                'user_id' => self::INT_FIELD_NOT_NULL,
                'type' => self::TINYINT_FIELD . ' DEFAULT 1',
                'created_at' => self::TIMESTAMP_FIELD,
            ], self::TABLE_OPTIONS_MB4);
            $this->createIndex('idx_user_video_like_video', $likeTable, 'user_video_id');
            $this->createIndex('idx_user_video_like_user', $likeTable, 'user_id');
            $this->addForeignKey('fk_user_video_like_video', $likeTable, 'user_video_id', $table, 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey('fk_user_video_like_user', $likeTable, 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        }
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_user_video_like_user', '{{%user_video_like}}');
        $this->dropForeignKey('fk_user_video_like_video', '{{%user_video_like}}');
        $this->dropTable('{{%user_video_like}}');
        $schema = $this->db->schema->getTableSchema('{{%user_video}}', true);
        if ($schema && isset($schema->columns['likes'])) {
            $this->dropColumn('{{%user_video}}', 'likes');
        }
    }
}
