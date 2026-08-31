<?php

use yii\db\Migration;

/**
 * Ускоряет выборку и блокировку доступных предметов конкретного пользователя.
 */
class m260831_120000_add_user_status_index_to_user_drop extends Migration
{
    public function safeUp()
    {
        $this->createIndex(
            'idx-user_drop-user_id-status',
            'user_drop',
            ['user_id', 'status']
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user_drop-user_id-status', 'user_drop');
    }
}
