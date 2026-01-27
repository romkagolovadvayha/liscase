<?php

use yii\db\Migration;

/**
 * Class m260120_140000_add_new_monitoring_fields_to_servers_table
 * Добавляет новые поля для мониторинга серверов
 */
class m260120_140000_add_new_monitoring_fields_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        
        if (!$schema) {
            return;
        }
        
        // Добавляем режим игры
        if (!$schema->getColumn('game_mode')) {
            $this->addColumn('{{%servers}}', 'game_mode', $this->string(255)->notNull()->defaultValue('vanilla')->comment('Режим игры'));
        }
        
        // Добавляем теги сервера в мониторинге
        if (!$schema->getColumn('monitoring_tags')) {
            $this->addColumn('{{%servers}}', 'monitoring_tags', $this->string(255)->notNull()->defaultValue('weekly, vanilla, EU, tut')->comment('Теги сервера в мониторинге'));
        }
        
        // Добавляем название сервера для вайпа (применяется в игре)
        if (!$schema->getColumn('wipe_server_name')) {
            $this->addColumn('{{%servers}}', 'wipe_server_name', $this->string(255)->null()->comment('Название сервера при вайпе (для игры)'));
        }
        
        // Добавляем описание сервера для вайпа (применяется в игре)
        if (!$schema->getColumn('wipe_server_description')) {
            $this->addColumn('{{%servers}}', 'wipe_server_description', $this->text()->null()->comment('Описание сервера при вайпе (для игры)'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema) {
            return;
        }

        if ($schema->getColumn('game_mode')) {
            $this->dropColumn('{{%servers}}', 'game_mode');
        }
        
        if ($schema->getColumn('monitoring_tags')) {
            $this->dropColumn('{{%servers}}', 'monitoring_tags');
        }
        
        if ($schema->getColumn('wipe_server_name')) {
            $this->dropColumn('{{%servers}}', 'wipe_server_name');
        }
        
        if ($schema->getColumn('wipe_server_description')) {
            $this->dropColumn('{{%servers}}', 'wipe_server_description');
        }
    }
}

