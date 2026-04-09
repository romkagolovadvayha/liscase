<?php

use console\components\migration\Migration;

/**
 * Если m260409_120000 упала на FK (3780), таблица могла остаться без внешних ключей.
 * Удаляем пустой «обломок», чтобы повторный migrate смог создать таблицу заново.
 */
class m260408_160000_drop_orphan_clan_plugin_cupboards_table extends Migration
{
    public function safeUp()
    {
        $table = '{{%clan_plugin_cupboards}}';
        if ($this->db->schema->getTableSchema($table) === null) {
            return;
        }

        $hasServerFk = false;
        foreach ($this->db->getSchema()->getTableForeignKeys($table, true) as $fk) {
            if ($fk->name === 'fk_clan_plugin_cupboards_server_id') {
                $hasServerFk = true;
                break;
            }
        }

        if ($hasServerFk) {
            return;
        }

        $this->dropTable($table);
    }

    public function safeDown()
    {
    }
}
