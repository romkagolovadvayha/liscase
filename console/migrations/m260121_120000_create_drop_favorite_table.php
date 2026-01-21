<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `drop_favorite`.
 */
class m260121_120000_create_drop_favorite_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('drop_favorite', [
            'id' => self::PRIMARY_KEY,
            'user_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID пользователя\'',
            'drop_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID товара (drop)\'',
            'created_at' => self::TIMESTAMP_FIELD . ' COMMENT \'Время добавления в избранное\'',
        ], self::TABLE_OPTIONS_MB4);

        // Уникальный индекс для user_id + drop_id (чтобы нельзя было добавить один товар дважды)
        $this->createIndex('idx-drop_favorite-unique_user_drop', 'drop_favorite', ['user_id', 'drop_id'], true);

        // Индексы для быстрого поиска
        $this->createIndex('idx-drop_favorite-user_id', 'drop_favorite', 'user_id');
        $this->createIndex('idx-drop_favorite-drop_id', 'drop_favorite', 'drop_id');
        $this->createIndex('idx-drop_favorite-created_at', 'drop_favorite', 'created_at');

        // Внешние ключи
        $this->addForeignKey(
            'fk-drop_favorite-user_id',
            'drop_favorite',
            'user_id',
            'user',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-drop_favorite-drop_id',
            'drop_favorite',
            'drop_id',
            'drop',
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
        $this->dropForeignKey('fk-drop_favorite-user_id', 'drop_favorite');
        $this->dropForeignKey('fk-drop_favorite-drop_id', 'drop_favorite');
        $this->dropTable('drop_favorite');
    }
}


