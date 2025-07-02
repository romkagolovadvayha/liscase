<?php

use console\components\migration\Migration;

class m250227_070406_task_result extends Migration
{
    public function up()
    {
        $this->addColumn('user_tasks', 'result', 'VARCHAR(300) DEFAULT NULL');
        $this->addColumn('tasks', 'promotion_id', self::INT_FIELD);
    }

    public function down()
    {

    }
}
