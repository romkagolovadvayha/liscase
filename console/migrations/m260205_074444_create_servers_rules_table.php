<?php

use yii\db\Migration;

/**
 * Class m260205_074444_create_servers_rules_table
 */
class m260205_074444_create_servers_rules_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_rules}}', [
            'id' => $this->primaryKey(),
            'category_id' => $this->integer()->notNull()->comment('ID категории'),
            'title' => $this->string(500)->null()->comment('Название правила (опционально)'),
            'content' => $this->text()->notNull()->comment('Содержание правила (HTML)'),
            'punishment' => $this->string(255)->null()->comment('Наказание за нарушение'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('Порядок сортировки'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_servers_rules_category_id', '{{%servers_rules}}', 'category_id');
        $this->createIndex('idx_servers_rules_sort', '{{%servers_rules}}', ['category_id', 'sort']);

        $this->addForeignKey(
            'fk_servers_rules_category_id',
            '{{%servers_rules}}',
            'category_id',
            '{{%servers_rules_categories}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_servers_rules_category_id', '{{%servers_rules}}');
        $this->dropTable('{{%servers_rules}}');
    }
}

