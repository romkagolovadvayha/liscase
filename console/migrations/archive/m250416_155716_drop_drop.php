<?php

use console\components\migration\Migration;

/**
 * Class m250416_155716_drop_drop
 */
class m250416_155716_drop_drop extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%drop_drop}}', [
            'id'             => self::PRIMARY_KEY,
            'drop_id'        => self::INT_FIELD,
            'parent_drop_id' => self::INT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);
        $this->addForeignKey('drop_drop_drop_id', 'drop_drop', 'drop_id',
                             'drop', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('drop_drop_parent_drop_id', 'drop_drop', 'parent_drop_id',
                             'drop', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
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
        echo "m250416_155716_drop_drop cannot be reverted.\n";

        return false;
    }
    */
}
