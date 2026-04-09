<?php

use console\components\migration\Migration;

/**
 * Переименование/перестановка UNIQUE под явный ключ: entity_id + server_id + wipe.
 * Для БД, где уже применена m260409_120000 с индексом uq_clan_plugin_cupboards_srv_wipe_entity.
 */
class m260410_090000_clan_plugin_cupboards_unique_entity_server_wipe extends Migration
{
    public function safeUp()
    {
        $table = '{{%clan_plugin_cupboards}}';
        if ($this->db->schema->getTableSchema($table) === null) {
            return;
        }

        $old = 'uq_clan_plugin_cupboards_srv_wipe_entity';
        $new = 'uq_clan_plugin_cupboards_entity_server_wipe';

        $byName = [];
        foreach ($this->db->getSchema()->getTableIndexes($table, true) as $idx) {
            $byName[$idx->name] = true;
        }

        if (isset($byName[$new])) {
            return;
        }

        if (isset($byName[$old])) {
            $this->dropIndex($old, $table);
        }

        $this->createIndex($new, $table, ['entity_id', 'server_id', 'wipe'], true);
    }

    public function safeDown()
    {
        $table = '{{%clan_plugin_cupboards}}';
        if ($this->db->schema->getTableSchema($table) === null) {
            return;
        }
        $new = 'uq_clan_plugin_cupboards_entity_server_wipe';
        $old = 'uq_clan_plugin_cupboards_srv_wipe_entity';

        $byName = [];
        foreach ($this->db->getSchema()->getTableIndexes($table, true) as $idx) {
            $byName[$idx->name] = true;
        }

        if (isset($byName[$new]) && !isset($byName[$old])) {
            $this->dropIndex($new, $table);
            $this->createIndex($old, $table, ['server_id', 'wipe', 'entity_id'], true);
        }
    }
}
