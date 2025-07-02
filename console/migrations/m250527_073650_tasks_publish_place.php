<?php

use console\components\migration\Migration;

class m250527_073650_tasks_publish_place extends Migration
{
    public function up()
    {
        $this->addColumn('tasks_publish_place', 'system_check_code', self::VARCHAR_FIELD);
    }

    public function down()
    {

    }
}
