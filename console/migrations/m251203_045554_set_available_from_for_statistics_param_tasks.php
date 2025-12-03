<?php

use yii\db\Migration;

/**
 * Class m251203_045554_set_available_from_for_statistics_param_tasks
 */
class m251203_045554_set_available_from_for_statistics_param_tasks extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Устанавливаем дату доступности 04.12.2025 22:00 для всех заданий с типом проверки "параметр статистики"
        $this->update(
            'tasks_v2',
            ['available_from' => '2025-12-04 22:00:00'],
            ['check_type' => 'statistics_param']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Откатываем изменения - устанавливаем available_from в NULL для этих заданий
        $this->update(
            'tasks_v2',
            ['available_from' => null],
            ['check_type' => 'statistics_param']
        );
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251203_045554_set_available_from_for_statistics_param_tasks cannot be reverted.\n";

        return false;
    }
    */
}
