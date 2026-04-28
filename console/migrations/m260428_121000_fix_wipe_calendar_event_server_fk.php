<?php

use console\components\migration\Migration;
use yii\db\ColumnSchema;
use yii\db\Schema;

/**
 * Исправление FK wipe_calendar_event.server_id → servers.id после errno 150
 * (несовпадение UNSIGNED vs signed INT у {@see servers.id}).
 */
class m260428_121000_fix_wipe_calendar_event_server_fk extends Migration
{
    public function safeUp()
    {
        $table = '{{%wipe_calendar_event}}';
        if ($this->db->getTableSchema($table, true) === null) {
            return;
        }

        try {
            $this->dropForeignKey('fk_wipe_calendar_event_server', $table);
        } catch (\Throwable $e) {
            // ключа ещё не было или имя другое
        }

        $serversSchema = $this->db->schema->getTableSchema('{{%servers}}', true);
        $serversIdColumn = $serversSchema !== null ? $serversSchema->getColumn('id') : null;
        $serverIdType = $this->resolveServersIdColumnType($serversIdColumn);

        $this->alterColumn($table, 'server_id', $serverIdType->null()->defaultValue(null));

        $this->addForeignKey(
            'fk_wipe_calendar_event_server',
            $table,
            'server_id',
            '{{%servers}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $table = '{{%wipe_calendar_event}}';
        if ($this->db->getTableSchema($table, true) === null) {
            return;
        }
        try {
            $this->dropForeignKey('fk_wipe_calendar_event_server', $table);
        } catch (\Throwable $e) {
        }
    }

    private function resolveServersIdColumnType(?ColumnSchema $column)
    {
        if ($column === null) {
            return $this->integer();
        }

        switch ($column->type) {
            case Schema::TYPE_BIGINT:
                $builder = $this->bigInteger();
                break;
            case Schema::TYPE_SMALLINT:
                $builder = $this->smallInteger();
                break;
            case Schema::TYPE_TINYINT:
                $builder = $this->tinyInteger();
                break;
            default:
                $builder = $this->integer();
        }

        if ($column->unsigned) {
            $builder->unsigned();
        }

        return $builder;
    }
}
