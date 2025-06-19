<?php

use console\components\migration\Migration;

/**
 * Class m250519_233057_user_mirror
 */
class m250519_233057_user_mirror extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('mirrors', [
            'id'          => self::PRIMARY_KEY,
            'steam_id'    => 'VARCHAR(19) NOT NULL',
            'mirror_name' => self::VARCHAR_FIELD,
            'created_at'  => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250519_233057_user_mirror cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250519_233057_user_mirror cannot be reverted.\n";

        return false;
    }
    */
}
