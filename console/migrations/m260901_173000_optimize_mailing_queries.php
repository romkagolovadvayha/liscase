<?php

use yii\db\Migration;

class m260901_173000_optimize_mailing_queries extends Migration
{
    public function safeUp()
    {
        $this->createIndex(
            'idx_user_mailing_telegram',
            'user',
            ['status', 'is_telegram_blocked', 'telegram_chat_id']
        );
        $this->createIndex(
            'idx_user_mailing_language',
            'user',
            ['status', 'current_language']
        );
        $this->createIndex('idx_user_mailing_ref_code', 'user', 'ref_code');
        $this->createIndex(
            'idx_telegram_constructor_status_created',
            'telegram_constructor',
            ['status', 'created_at']
        );
        $this->createIndex('idx_telegram_constructor_audience', 'telegram_constructor', 'audience_id');
        $this->createIndex('idx_telegram_constructor_bot', 'telegram_constructor', 'bot_id');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_telegram_constructor_bot', 'telegram_constructor');
        $this->dropIndex('idx_telegram_constructor_audience', 'telegram_constructor');
        $this->dropIndex('idx_telegram_constructor_status_created', 'telegram_constructor');
        $this->dropIndex('idx_user_mailing_ref_code', 'user');
        $this->dropIndex('idx_user_mailing_language', 'user');
        $this->dropIndex('idx_user_mailing_telegram', 'user');
    }
}
