<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `user_map`.
 */
class m250115_155212_create_user_map_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Создание таблицы user_map
        $this->createTable('user_map', [
            'id'           => self::PRIMARY_KEY,
            'user_id' => self::INT_FIELD, // ID пользователя
            'map_id' => self::INT_FIELD, // ID карты
            'vote' => $this->integer()->notNull()->defaultValue(0), // Голос (1 - плюс, -1 - минус, 0 - нет голоса)
            'created_at' =>self::TIMESTAMP_FIELD, // Дата и время создания записи
        ]);

        // Добавляем внешние ключи
        $this->addForeignKey(
            'fk_user_map_user_id',
            'user_map',
            'user_id',
            'user', // Таблица пользователей
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_user_map_map_id',
            'user_map',
            'map_id',
            'map', // Таблица карт
            'id',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем внешние ключи
        $this->dropForeignKey('fk_user_map_user_id', 'user_map');
        $this->dropForeignKey('fk_user_map_map_id', 'user_map');

        // Удаляем таблицу
        $this->dropTable('user_map');
    }
}