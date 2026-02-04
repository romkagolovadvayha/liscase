<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%servers_statistics_history}}`.
 * Таблица для хранения истории статистики серверов (онлайн, очередь, подключающиеся) каждый час.
 */
class m260204_104517_create_servers_statistics_history_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_statistics_history}}', [
            'id' => $this->primaryKey(),
            'server_id' => $this->integer()->notNull()->comment('ID сервера'),
            'players' => $this->integer()->notNull()->defaultValue(0)->comment('Текущий онлайн'),
            'joined' => $this->integer()->notNull()->defaultValue(0)->comment('Игроки в очереди'),
            'queued' => $this->integer()->notNull()->defaultValue(0)->comment('Подключающиеся'),
            'max' => $this->integer()->notNull()->defaultValue(0)->comment('Максимальный онлайн'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->comment('Время записи'),
        ]);

        // Индексы для быстрого поиска
        $this->createIndex('idx-servers_statistics_history-server_id', '{{%servers_statistics_history}}', 'server_id');
        $this->createIndex('idx-servers_statistics_history-created_at', '{{%servers_statistics_history}}', 'created_at');
        $this->createIndex('idx-servers_statistics_history-server_created', '{{%servers_statistics_history}}', ['server_id', 'created_at']);

        // Внешний ключ на таблицу servers
        $this->addForeignKey(
            'fk-servers_statistics_history-server_id',
            '{{%servers_statistics_history}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем внешний ключ
        $this->dropForeignKey('fk-servers_statistics_history-server_id', '{{%servers_statistics_history}}');
        
        // Удаляем таблицу
        $this->dropTable('{{%servers_statistics_history}}');
    }
}
