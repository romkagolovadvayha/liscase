<?php

use console\components\migration\Migration;

class m250305_172828_system_check_code extends Migration
{
    public function up()
    {
        $this->addColumn('tasks_projects', 'system_check_code', self::VARCHAR_FIELD);
    }

    public function down()
    {

    }
}
