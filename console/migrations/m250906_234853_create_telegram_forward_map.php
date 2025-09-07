<?php

use yii\db\Migration;

/**
 * Class m250906_234853_create_telegram_forward_map
 */
class m250906_234853_create_telegram_forward_map extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%telegram_source_cursor}}', [
            'source'         => $this->string(128)->notNull(), // username без @
            'last_message_id'=> $this->integer()->notNull()->defaultValue(0),
            'updated_at'     => $this->integer()->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_tg_source_cursor',
            '{{%telegram_source_cursor}}',
            'source'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250906_234853_create_telegram_forward_map cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250906_234853_create_telegram_forward_map cannot be reverted.\n";

        return false;
    }
    */
}
