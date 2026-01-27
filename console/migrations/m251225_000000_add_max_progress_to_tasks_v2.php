<?php

use console\components\migration\Migration;

/**
 * Class m251225_000000_add_max_progress_to_tasks_v2
 * Добавление поля max_progress в таблицу tasks_v2
 */
class m251225_000000_add_max_progress_to_tasks_v2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('tasks_v2', 'max_progress', self::INT_FIELD . ' DEFAULT NULL COMMENT \'Максимальный прогресс для отображения (для заданий с прогрессом)\'');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('tasks_v2', 'max_progress');
    }
}

