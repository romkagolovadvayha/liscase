<?php

use console\components\migration\Migration;

/**
 * Очки базы по формуле из блоков (МВК/железо/камень/дерево).
 */
class m260414_100000_clan_plugin_cupboards_score extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%clan_plugin_cupboards}}');
        if ($table === null || $table->getColumn('score') !== null) {
            return;
        }
        $this->addColumn(
            '{{%clan_plugin_cupboards}}',
            'score',
            $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('Очки за базу: ceil((МВК*15 + железо*4 + камень*2 + дерево*0.2)/100)')
        );
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%clan_plugin_cupboards}}', true);
        if ($table !== null && $table->getColumn('score') !== null) {
            $this->dropColumn('{{%clan_plugin_cupboards}}', 'score');
        }
    }
}
