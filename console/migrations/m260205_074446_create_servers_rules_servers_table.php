<?php

use yii\db\Migration;

/**
 * Class m260205_074446_create_servers_rules_servers_table
 * Связующая таблица для many-to-many связи между правилами и серверами
 */
class m260205_074446_create_servers_rules_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_rules_servers}}', [
            'id' => $this->primaryKey(),
            'rule_id' => $this->integer()->notNull()->comment('ID правила'),
            'server_id' => $this->integer()->notNull()->comment('ID сервера'),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_servers_rules_servers_rule_id', '{{%servers_rules_servers}}', 'rule_id');
        $this->createIndex('idx_servers_rules_servers_server_id', '{{%servers_rules_servers}}', 'server_id');
        $this->createIndex('idx_servers_rules_servers_unique', '{{%servers_rules_servers}}', ['rule_id', 'server_id'], true);

        $this->addForeignKey(
            'fk_servers_rules_servers_rule_id',
            '{{%servers_rules_servers}}',
            'rule_id',
            '{{%servers_rules}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_servers_rules_servers_server_id',
            '{{%servers_rules_servers}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Если поле server_id еще существует (для обратной совместимости при миграции)
        // Переносим данные из старого поля server_id в новую таблицу
        $tableSchema = $this->db->schema->getTableSchema('{{%servers_rules}}');
        if ($tableSchema && isset($tableSchema->columns['server_id'])) {
            $this->execute("
                INSERT INTO {{%servers_rules_servers}} (rule_id, server_id, created_at)
                SELECT id, server_id, created_at
                FROM {{%servers_rules}}
                WHERE server_id IS NOT NULL
            ");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_servers_rules_servers_server_id', '{{%servers_rules_servers}}');
        $this->dropForeignKey('fk_servers_rules_servers_rule_id', '{{%servers_rules_servers}}');
        $this->dropTable('{{%servers_rules_servers}}');
    }
}

