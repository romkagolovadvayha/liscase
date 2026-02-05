<?php

use yii\db\Migration;

/**
 * Class m260205_074447_remove_server_id_from_servers_rules
 * Удаление поля server_id из таблицы servers_rules, так как теперь используется связующая таблица servers_rules_servers
 */
class m260205_074447_remove_server_id_from_servers_rules extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableSchema = $this->db->schema->getTableSchema('{{%servers_rules}}');
        
        // Проверяем существование колонки перед удалением
        if ($tableSchema && isset($tableSchema->columns['server_id'])) {
            // Проверяем и удаляем внешний ключ, если существует
            $foreignKeys = $this->db->schema->getTableForeignKeys('{{%servers_rules}}');
            $fkExists = false;
            foreach ($foreignKeys as $fk) {
                if ($fk->name === 'fk_servers_rules_server_id') {
                    $fkExists = true;
                    break;
                }
            }
            if ($fkExists) {
                $this->dropForeignKey('fk_servers_rules_server_id', '{{%servers_rules}}');
            }
            
            // Проверяем и удаляем индекс, если существует
            $indexes = $this->db->schema->getTableIndexes('{{%servers_rules}}');
            $idxExists = false;
            foreach ($indexes as $idx) {
                if ($idx->name === 'idx_servers_rules_server_id') {
                    $idxExists = true;
                    break;
                }
            }
            if ($idxExists) {
                $this->dropIndex('idx_servers_rules_server_id', '{{%servers_rules}}');
            }
            
            // Удаляем поле
            $this->dropColumn('{{%servers_rules}}', 'server_id');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Восстанавливаем поле
        $this->addColumn('{{%servers_rules}}', 'server_id', $this->integer()->null()->comment('ID сервера (NULL для общих правил)'));
        
        // Восстанавливаем индекс
        $this->createIndex('idx_servers_rules_server_id', '{{%servers_rules}}', 'server_id');
        
        // Восстанавливаем внешний ключ
        $this->addForeignKey(
            'fk_servers_rules_server_id',
            '{{%servers_rules}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }
}

