<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%drop_type}}`.
 */
class m221124_115238_create_drop_type_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%drop_type}}', [
            'id' => self::PRIMARY_KEY,
            'name' => self::VARCHAR_FIELD,
            'type' => self::VARCHAR_FIELD,
        ]);
        $this->addColumn('drop', 'type_id', self::INT_FIELD . ' AFTER name');
        $this->addForeignKey('fk_drop_type_id', 'drop', 'type_id',
            'drop_type', 'id', NULL, NULL);
        $this->addColumn('drop', 'eng_name', self::VARCHAR_FIELD . ' AFTER name');
        $this->addColumn('drop', 'quality', self::VARCHAR_FIELD . ' AFTER name');
        $this->addColumn('drop', 'description', 'TEXT NOT NULL COLLATE utf8mb4_unicode_ci' . ' AFTER name');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }
}
