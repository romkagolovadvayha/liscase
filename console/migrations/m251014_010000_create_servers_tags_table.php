<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%servers_tags}}`.
 */
class m251014_010000_create_servers_tags_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_tags}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull()->comment('Название тега'),
            'title' => $this->string(255)->comment('Заголовок (title)'),
            'link_name' => $this->string(255)->notNull()->comment('Название для ссылки'),
            'short_description' => $this->string(500)->comment('Краткое описание'),
            'description' => $this->text()->comment('Полное описание'),
            'color' => $this->string(7)->defaultValue('#3498db')->comment('Цвет тега (HEX)'),
            'sort' => $this->integer()->defaultValue(0)->comment('Сортировка'),
            'status' => $this->tinyInteger()->defaultValue(1)->comment('Статус (0-неактивен, 1-активен)'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        // Индексы
        $this->createIndex('idx-servers_tags-status', '{{%servers_tags}}', 'status');
        $this->createIndex('idx-servers_tags-sort', '{{%servers_tags}}', 'sort');
        $this->createIndex('idx-servers_tags-link_name', '{{%servers_tags}}', 'link_name', true);

        // Связующая таблица серверов и тегов
        $this->createTable('{{%servers_tags_relation}}', [
            'id' => $this->primaryKey(),
            'server_id' => $this->integer()->notNull()->comment('ID сервера'),
            'tag_id' => $this->integer()->notNull()->comment('ID тега'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // Индексы для связующей таблицы
        $this->createIndex('idx-servers_tags_relation-server_id', '{{%servers_tags_relation}}', 'server_id');
        $this->createIndex('idx-servers_tags_relation-tag_id', '{{%servers_tags_relation}}', 'tag_id');
        $this->createIndex('idx-servers_tags_relation-unique', '{{%servers_tags_relation}}', ['server_id', 'tag_id'], true);

        // Внешние ключи
        $this->addForeignKey(
            'fk-servers_tags_relation-server_id',
            '{{%servers_tags_relation}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-servers_tags_relation-tag_id',
            '{{%servers_tags_relation}}',
            'tag_id',
            '{{%servers_tags}}',
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
        // Удаляем внешние ключи
        $this->dropForeignKey('fk-servers_tags_relation-tag_id', '{{%servers_tags_relation}}');
        $this->dropForeignKey('fk-servers_tags_relation-server_id', '{{%servers_tags_relation}}');

        // Удаляем таблицы
        $this->dropTable('{{%servers_tags_relation}}');
        $this->dropTable('{{%servers_tags}}');
    }
}

