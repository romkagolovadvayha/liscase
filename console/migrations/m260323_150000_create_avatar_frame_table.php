<?php

use console\components\migration\Migration;

class m260323_150000_create_avatar_frame_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%avatar_frame}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'image_key' => $this->string(512)->notNull()->comment('S3 key, e.g. uploads/avatar-frames/frame_xxx.png'),
            'is_active' => $this->boolean()->notNull()->defaultValue(1),
            'sort' => $this->integer()->notNull()->defaultValue(100),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_avatar_frame_active_sort', '{{%avatar_frame}}', ['is_active', 'sort']);
    }

    public function safeDown()
    {
        $this->dropIndex('idx_avatar_frame_active_sort', '{{%avatar_frame}}');
        $this->dropTable('{{%avatar_frame}}');
    }
}

