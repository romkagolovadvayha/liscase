<?php

use console\components\migration\Migration;

class m250217_070256_user_tasks extends Migration
{
    public function up()
    {
        $this->createTable('{{%user_tasks}}', [
            'id'          => $this->primaryKey(),
            'task_id'     => self::INT_FIELD_NOT_NULL,
            'user_id'     => self::INT_FIELD_NOT_NULL,
            'status'      => self::TINYINT_1_FIELD,
            'awarded'     => self::TINYINT_1_FIELD,
            'finished_at' => self::TIMESTAMP_FIELD,
            'created_at'  => self::TIMESTAMP_FIELD,
        ]);

        $this->addForeignKey('fk_user_tasks_user_id', 'user_tasks', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_user_tasks_task_id', 'user_tasks', 'task_id',
                             'tasks', 'id', 'CASCADE', 'CASCADE');
    }

    public function down()
    {

    }
}
