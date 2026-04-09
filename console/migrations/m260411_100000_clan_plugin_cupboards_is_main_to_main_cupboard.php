<?php

use console\components\migration\Migration;

/**
 * Переименование is_main → main_cupboard (если таблица создана старой версией m260409).
 */
class m260411_100000_clan_plugin_cupboards_is_main_to_main_cupboard extends Migration
{
    public function safeUp()
    {
        $table = '{{%clan_plugin_cupboards}}';
        $schema = $this->db->schema->getTableSchema($table);
        if ($schema === null) {
            return;
        }
        if ($schema->getColumn('main_cupboard') !== null) {
            return;
        }
        if ($schema->getColumn('is_main') !== null) {
            $this->renameColumn($table, 'is_main', 'main_cupboard');
        } else {
            $this->addColumn(
                $table,
                'main_cupboard',
                $this->tinyInteger(1)->unsigned()->notNull()->defaultValue(0)->comment('Главный шкаф клана по max protected_blocks')
            );
        }
    }

    public function safeDown()
    {
        $table = '{{%clan_plugin_cupboards}}';
        $schema = $this->db->schema->getTableSchema($table);
        if ($schema === null || $schema->getColumn('main_cupboard') === null) {
            return;
        }
        if ($schema->getColumn('is_main') === null) {
            $this->renameColumn($table, 'main_cupboard', 'is_main');
        }
    }
}
