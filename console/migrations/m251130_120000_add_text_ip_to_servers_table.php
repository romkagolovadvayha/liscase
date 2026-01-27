<?php

use yii\db\Migration;

/**
 * Class m251130_120000_add_text_ip_to_servers_table
 */
class m251130_120000_add_text_ip_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        
        if ($schema && !$schema->getColumn('text_ip')) {
            $this->addColumn('{{%servers}}', 'text_ip', $this->string(255)->null()->comment('Текстовый IP адрес'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema) {
            return;
        }

        if ($schema->getColumn('text_ip')) {
            $this->dropColumn('{{%servers}}', 'text_ip');
        }
    }
}

