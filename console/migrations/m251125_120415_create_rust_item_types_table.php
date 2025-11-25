<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%rust_item_types}}`.
 */
class m251125_120415_create_rust_item_types_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%rust_item_types}}', [
            'id' => $this->primaryKey(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%rust_item_types}}');
    }
}
