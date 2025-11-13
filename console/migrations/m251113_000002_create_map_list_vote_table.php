<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%map_list_vote}}`.
 */
class m251113_000002_create_map_list_vote_table extends Migration
{
    public function safeUp()
    {
        $tableSchema = $this->db->schema->getTableSchema('{{%map_list_vote}}', true);
        if ($tableSchema !== null) {
            $this->dropTable('{{%map_list_vote}}');
        }

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        $mapListIdColumn = $this->db->schema->getTableSchema('{{%map_list}}')->getColumn('id');
        $serversIdColumn = $this->db->schema->getTableSchema('{{%servers}}')->getColumn('id');
        $userIdColumn = $this->db->schema->getTableSchema('{{%user}}')->getColumn('id');

        $mapListIdType = $mapListIdColumn && $mapListIdColumn->unsigned ? $this->integer()->unsigned() : $this->integer();
        $serverIdType = $serversIdColumn && $serversIdColumn->unsigned ? $this->integer()->unsigned() : $this->integer();
        $userIdType = $userIdColumn && $userIdColumn->unsigned ? $this->integer()->unsigned() : $this->integer();

        $this->createTable('{{%map_list_vote}}', [
            'id' => $this->primaryKey(),
            'map_list_id' => $mapListIdType->notNull(),
            'server_id' => $serverIdType->notNull(),
            'user_id' => $userIdType->notNull(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $tableOptions);

        $this->createIndex('idx-map_list_vote-map_list_id', '{{%map_list_vote}}', 'map_list_id');
        $this->createIndex('idx-map_list_vote-server_id', '{{%map_list_vote}}', 'server_id');
        $this->createIndex('idx-map_list_vote-user_id', '{{%map_list_vote}}', 'user_id');
        $this->createIndex(
            'ux-map_list_vote-unique_vote',
            '{{%map_list_vote}}',
            ['map_list_id', 'server_id', 'user_id'],
            true
        );

        $this->addForeignKey(
            'fk-map_list_vote-map_list_id',
            '{{%map_list_vote}}',
            'map_list_id',
            '{{%map_list}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-map_list_vote-server_id',
            '{{%map_list_vote}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-map_list_vote-user_id',
            '{{%map_list_vote}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-map_list_vote-user_id', '{{%map_list_vote}}');
        $this->dropForeignKey('fk-map_list_vote-server_id', '{{%map_list_vote}}');
        $this->dropForeignKey('fk-map_list_vote-map_list_id', '{{%map_list_vote}}');

        $this->dropIndex('ux-map_list_vote-unique_vote', '{{%map_list_vote}}');
        $this->dropIndex('idx-map_list_vote-user_id', '{{%map_list_vote}}');
        $this->dropIndex('idx-map_list_vote-server_id', '{{%map_list_vote}}');
        $this->dropIndex('idx-map_list_vote-map_list_id', '{{%map_list_vote}}');

        $this->dropTable('{{%map_list_vote}}');
    }
}

