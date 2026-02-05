<?php

use yii\db\Migration;

/**
 * Class m260205_074443_create_servers_rules_categories_table
 */
class m260205_074443_create_servers_rules_categories_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_rules_categories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull()->comment('Название категории'),
            'icon' => $this->string(255)->null()->comment('Иконка категории'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('Порядок сортировки'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_servers_rules_categories_sort', '{{%servers_rules_categories}}', 'sort');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%servers_rules_categories}}');
    }
}

