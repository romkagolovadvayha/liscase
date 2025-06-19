<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%box_drop}}`.
 */
class m221114_210429_create_box_drop_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%box_drop}}', [
            'id'             => self::PRIMARY_KEY,
            'drop_id'        => self::INT_FIELD,
            'box_id' => self::INT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%box_drop}}');
    }
}
