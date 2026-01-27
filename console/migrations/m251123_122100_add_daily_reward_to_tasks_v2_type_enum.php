<?php

use console\components\migration\Migration;

/**
 * Class m251123_122100_add_daily_reward_to_tasks_v2_type_enum
 * Добавляет значение 'daily_reward' в enum поля type таблицы tasks_v2
 */
class m251123_122100_add_daily_reward_to_tasks_v2_type_enum extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем новое значение 'daily_reward' в enum поля type
        $this->execute("ALTER TABLE `tasks_v2` MODIFY COLUMN `type` ENUM('one_time','repeatable','daily_reward') NOT NULL DEFAULT 'one_time' COMMENT 'Тип задания'");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем значение 'daily_reward' из enum (если нет записей с этим типом)
        // ВНИМАНИЕ: Это может вызвать ошибку, если есть записи с типом 'daily_reward'
        $this->execute("ALTER TABLE `tasks_v2` MODIFY COLUMN `type` ENUM('one_time','repeatable') NOT NULL DEFAULT 'one_time' COMMENT 'Тип задания'");
    }
}

