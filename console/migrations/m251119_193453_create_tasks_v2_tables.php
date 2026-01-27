<?php

use console\components\migration\Migration;

/**
 * Class m251119_193453_create_tasks_v2_tables
 * Creates tables for new tasks v2 system
 */
class m251119_193453_create_tasks_v2_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Таблица заданий
        $this->createTable('tasks_v2', [
            'id' => self::PRIMARY_KEY,
            'title' => 'VARCHAR(255) NOT NULL COMMENT \'Название задания\'',
            'short_description' => 'TEXT DEFAULT NULL COMMENT \'Краткое описание для карточки\'',
            'full_description' => 'TEXT DEFAULT NULL COMMENT \'Полное описание для модального окна\'',
            'type' => 'ENUM(\'one_time\',\'repeatable\') NOT NULL DEFAULT \'one_time\' COMMENT \'Тип задания\'',
            'check_type' => 'VARCHAR(100) NOT NULL COMMENT \'Тип проверки (vk_subscribe_group, telegram_connected, discord_join, kill_bots_count, invite_friend, custom_manual)\'',
            'check_params' => 'JSON DEFAULT NULL COMMENT \'Параметры проверки в JSON формате\'',
            'reward_type' => 'ENUM(\'item\',\'currency\') NOT NULL DEFAULT \'currency\' COMMENT \'Тип награды\'',
            'reward_item_id' => self::INT_FIELD . ' COMMENT \'ID товара из магазина (если reward_type = item)\'',
            'reward_currency' => 'VARCHAR(50) DEFAULT NULL COMMENT \'Тип баланса (если reward_type = currency)\'',
            'reward_amount' => 'DECIMAL(18,2) DEFAULT NULL COMMENT \'Сумма награды\'',
            'per_user_limit' => self::INT_FIELD . ' COMMENT \'Лимит выполнений на пользователя (для repeatable, NULL = без лимита)\'',
            'global_limit' => self::INT_FIELD . ' COMMENT \'Общий лимит выполнений задания (NULL = без лимита)\'',
            'global_completed' => self::INT_FIELD_NOT_NULL . ' DEFAULT 0 COMMENT \'Количество выполнений\'',
            'image_path' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Путь к изображению задания\'',
            'button_text' => 'VARCHAR(100) DEFAULT \'Проверить\' COMMENT \'Текст основной кнопки в модалке\'',
            'extra_buttons' => 'JSON DEFAULT NULL COMMENT \'Дополнительные кнопки-ссылки [{label,url}, ...]\'',
            'is_active' => 'TINYINT(1) DEFAULT 1 COMMENT \'Активно ли задание (0-нет, 1-да)\'',
            'is_visible_for_guests' => 'TINYINT(1) DEFAULT 0 COMMENT \'Видимо ли задание для неавторизованных (0-нет, 1-да)\'',
            'sort' => self::INT_FIELD_NOT_NULL . ' DEFAULT 0 COMMENT \'Сортировка\'',
            'created_at' => self::TIMESTAMP_FIELD,
            'updated_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-tasks_v2-type', 'tasks_v2', 'type');
        $this->createIndex('idx-tasks_v2-check_type', 'tasks_v2', 'check_type');
        $this->createIndex('idx-tasks_v2-is_active', 'tasks_v2', 'is_active');
        $this->createIndex('idx-tasks_v2-sort', 'tasks_v2', 'sort');
        $this->createIndex('idx-tasks_v2-reward_type', 'tasks_v2', 'reward_type');

        // Таблица выполнений заданий пользователями
        $this->createTable('task_v2_user_completion', [
            'id' => self::PRIMARY_KEY,
            'task_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID задания\'',
            'user_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID пользователя\'',
            'count_completed' => self::INT_FIELD_NOT_NULL . ' DEFAULT 0 COMMENT \'Количество выполнений\'',
            'last_completed' => self::TIMESTAMP_FIELD . ' COMMENT \'Дата последнего выполнения\'',
            'created_at' => self::TIMESTAMP_FIELD,
            'updated_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-task_v2_user_completion-task_id', 'task_v2_user_completion', 'task_id');
        $this->createIndex('idx-task_v2_user_completion-user_id', 'task_v2_user_completion', 'user_id');
        $this->createIndex('idx-task_v2_user_completion-last_completed', 'task_v2_user_completion', 'last_completed');
        
        // Уникальный индекс для пары task_id + user_id
        $this->createIndex('idx-task_v2_user_completion-unique', 'task_v2_user_completion', ['task_id', 'user_id'], true);

        // Внешние ключи
        $this->addForeignKey(
            'fk-task_v2_user_completion-task_id',
            'task_v2_user_completion',
            'task_id',
            'tasks_v2',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-task_v2_user_completion-user_id',
            'task_v2_user_completion',
            'user_id',
            'user',
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
        $this->dropForeignKey('fk-task_v2_user_completion-user_id', 'task_v2_user_completion');
        $this->dropForeignKey('fk-task_v2_user_completion-task_id', 'task_v2_user_completion');
        $this->dropTable('task_v2_user_completion');
        $this->dropTable('tasks_v2');
    }
}
