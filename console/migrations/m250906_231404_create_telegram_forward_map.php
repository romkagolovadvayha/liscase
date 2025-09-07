<?php

use yii\db\Migration;

/**
 * Class m250906_231404_create_telegram_forward_map
 */
class m250906_231404_create_telegram_forward_map extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%telegram_forward_map}}', [
            'source_chat_id'     => $this->string(64)->notNull(),
            'source_message_id'  => $this->integer()->notNull(),
            'target_chat_id'     => $this->string(64)->notNull(),
            'target_message_id'  => $this->integer()->notNull(),
            'media_group_id'     => $this->string(64)->null(),
            'created_at'         => $this->integer()->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_tg_forward_map',
            '{{%telegram_forward_map}}',
            ['source_chat_id', 'source_message_id']
        );

        $this->createIndex(
            'idx_tg_forward_map_media_group',
            '{{%telegram_forward_map}}',
            'media_group_id'
        );
    }

    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250906_231404_create_telegram_forward_map cannot be reverted.\n";

        return false;
    }
    */
}
