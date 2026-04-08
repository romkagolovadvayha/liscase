<?php

use yii\db\Migration;

/**
 * Флаг «система кланов» на сервере (админка + API).
 */
class m260408_120000_add_clans_enabled_to_servers_table extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || $schema->getColumn('clans_enabled')) {
            return;
        }
        $this->addColumn(
            '{{%servers}}',
            'clans_enabled',
            $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('Система кланов включена')
        );
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema || !$schema->getColumn('clans_enabled')) {
            return;
        }
        $this->dropColumn('{{%servers}}', 'clans_enabled');
    }
}
