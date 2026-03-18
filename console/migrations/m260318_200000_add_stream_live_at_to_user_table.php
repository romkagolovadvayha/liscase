<?php

use console\components\migration\Migration;

/**
 * Дата последнего обнаружения стримера в эфире (обновляется кроном раз в 3 мин).
 */
class m260318_200000_add_stream_live_at_to_user_table extends Migration
{
    public function safeUp()
    {
        if (!$this->db->schema->getTableSchema('{{%user}}')->getColumn('stream_live_at')) {
            $this->addColumn(
                '{{%user}}',
                'stream_live_at',
                $this->dateTime()->null()->comment('Дата последнего онлайна в эфире (Twitch/Kick)')
            );
        }
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'stream_live_at');
    }
}
