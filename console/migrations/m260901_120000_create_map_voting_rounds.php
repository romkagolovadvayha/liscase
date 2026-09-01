<?php

use yii\db\Migration;
use yii\db\Schema;

/**
 * Isolates map votes by server and wipe.
 *
 * The old implementation counted every vote ever cast for a server. That made
 * maps from previous Rust protocols eligible in a new vote. A round records
 * the exact RustMaps protocol and the exact candidate set shown to players.
 */
class m260901_120000_create_map_voting_rounds extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        $this->createTable('{{%map_voting_round}}', [
            'id' => $this->primaryKey(),
            'server_id' => $this->columnTypeFor('{{%servers}}', 'id')->notNull(),
            'target_wipe_at' => $this->dateTime()->notNull(),
            'is_staging' => $this->boolean()->notNull()->defaultValue(false),
            'save_version' => $this->integer()->null(),
            'status' => $this->string(20)->notNull()->defaultValue('generating'),
            'winning_map_list_id' => $this->columnTypeFor('{{%map_list}}', 'id')->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'opened_at' => $this->dateTime()->null(),
            'fixed_at' => $this->dateTime()->null(),
        ], $tableOptions);

        $this->createIndex(
            'idx-map_voting_round-server_target',
            '{{%map_voting_round}}',
            ['server_id', 'target_wipe_at']
        );
        $this->createIndex(
            'idx-map_voting_round-server_status',
            '{{%map_voting_round}}',
            ['server_id', 'status']
        );
        $this->addForeignKey(
            'fk-map_voting_round-server_id',
            '{{%map_voting_round}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-map_voting_round-winning_map_list_id',
            '{{%map_voting_round}}',
            'winning_map_list_id',
            '{{%map_list}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%map_voting_round_map}}', [
            'round_id' => $this->integer()->notNull(),
            'map_list_id' => $this->columnTypeFor('{{%map_list}}', 'id')->notNull(),
            'position' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'PRIMARY KEY ([[round_id]], [[map_list_id]])',
        ], $tableOptions);
        $this->createIndex(
            'idx-map_voting_round_map-map_list_id',
            '{{%map_voting_round_map}}',
            'map_list_id'
        );
        $this->addForeignKey(
            'fk-map_voting_round_map-round_id',
            '{{%map_voting_round_map}}',
            'round_id',
            '{{%map_voting_round}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-map_voting_round_map-map_list_id',
            '{{%map_voting_round_map}}',
            'map_list_id',
            '{{%map_list}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn(
            '{{%map_list_vote}}',
            'round_id',
            $this->integer()->null()->after('server_id')
        );
        $this->createIndex('idx-map_list_vote-round_id', '{{%map_list_vote}}', 'round_id');
        $this->addForeignKey(
            'fk-map_list_vote-round_id',
            '{{%map_list_vote}}',
            'round_id',
            '{{%map_voting_round}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Historical rows deliberately keep round_id = NULL. They remain as an
        // audit trail but can no longer influence a newly generated vote.
        $this->dropIndex('ux-map_list_vote-unique_vote', '{{%map_list_vote}}');
        $this->createIndex(
            'ux-map_list_vote-round_unique_vote',
            '{{%map_list_vote}}',
            ['round_id', 'map_list_id', 'user_id'],
            true
        );
    }

    public function safeDown()
    {
        // Votes created by the round-aware schema cannot be represented by the
        // legacy unique key across multiple wipes.
        $this->delete('{{%map_list_vote}}', ['not', ['round_id' => null]]);
        $this->dropIndex('ux-map_list_vote-round_unique_vote', '{{%map_list_vote}}');
        $this->createIndex(
            'ux-map_list_vote-unique_vote',
            '{{%map_list_vote}}',
            ['map_list_id', 'server_id', 'user_id'],
            true
        );
        $this->dropForeignKey('fk-map_list_vote-round_id', '{{%map_list_vote}}');
        $this->dropIndex('idx-map_list_vote-round_id', '{{%map_list_vote}}');
        $this->dropColumn('{{%map_list_vote}}', 'round_id');

        $this->dropForeignKey('fk-map_voting_round_map-map_list_id', '{{%map_voting_round_map}}');
        $this->dropForeignKey('fk-map_voting_round_map-round_id', '{{%map_voting_round_map}}');
        $this->dropTable('{{%map_voting_round_map}}');

        $this->dropForeignKey('fk-map_voting_round-winning_map_list_id', '{{%map_voting_round}}');
        $this->dropForeignKey('fk-map_voting_round-server_id', '{{%map_voting_round}}');
        $this->dropTable('{{%map_voting_round}}');
    }

    private function columnTypeFor(string $table, string $column)
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        $source = $schema ? $schema->getColumn($column) : null;

        if ($source && $source->type === Schema::TYPE_BIGINT) {
            $builder = $this->bigInteger();
        } elseif ($source && $source->type === Schema::TYPE_SMALLINT) {
            $builder = $this->smallInteger();
        } else {
            $builder = $this->integer();
        }

        if ($source && $source->unsigned) {
            $builder->unsigned();
        }

        return $builder;
    }
}
