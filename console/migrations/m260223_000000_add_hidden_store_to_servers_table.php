<?php

use yii\db\Migration;

/**
 * Добавляет признак «скрытый магазин» в таблицу servers.
 */
class m260223_000000_add_hidden_store_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || $schema->getColumn('hidden_store')) {
            return;
        }
        $this->addColumn('{{%servers}}', 'hidden_store', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('Скрытый магазин'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || !$schema->getColumn('hidden_store')) {
            return;
        }
        $this->dropColumn('{{%servers}}', 'hidden_store');
    }
}
