<?php

use console\components\migration\Migration;
use yii\db\ColumnSchema;
use yii\db\Schema;

/**
 * События календаря вайпов (админка), без связи с полями {@see \common\models\servers\Servers}.
 *
 * {@see servers.id} в БД — signed INT (как в {@see m260204_104517_create_servers_statistics_history_table}),
 * поэтому {@see server_id} должен совпадать по типу/знаку, иначе MySQL errno 150.
 */
class m260428_120000_wipe_calendar_event extends Migration
{
    public function safeUp()
    {
        $table = '{{%wipe_calendar_event}}';
        // Таблица могла остаться без FK после частично проваленного safeUp (DDL без транзакции).
        if ($this->db->getTableSchema($table, true) !== null) {
            echo "  > skip createTable: {$table} already exists (fix FK in m260428_121000)\n";

            return;
        }

        $serversSchema = $this->db->schema->getTableSchema('{{%servers}}', true);
        $serversIdColumn = $serversSchema !== null ? $serversSchema->getColumn('id') : null;
        $serverIdType = $this->resolveServersIdColumnType($serversIdColumn);

        $this->createTable($table, [
            'id' => $this->primaryKey()->unsigned(),
            'event_type' => $this->string(32)->notNull()->comment('map_wipe|global_wipe|game_update|custom'),
            'server_id' => $serverIdType->null()->defaultValue(null),
            'title' => $this->string(255)->null()->defaultValue(null),
            'event_at' => $this->dateTime()->notNull()->comment('Europe/Moscow semantics in app'),
            'created_at' => $this->dateTime()->null()->defaultValue(null),
            'updated_at' => $this->dateTime()->null()->defaultValue(null),
        ], Migration::TABLE_OPTIONS_MB4);

        $this->createIndex('idx_wipe_calendar_event_at', $table, 'event_at');
        $this->createIndex('idx_wipe_calendar_event_server', $table, 'server_id');

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
        $this->dropTable($table);
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
