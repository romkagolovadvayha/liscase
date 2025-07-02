<?php

use console\components\migration\Migration;

class m250305_172137_is_visibility_name extends Migration
{
    public function up()
    {
        $this->addColumn('tasks_projects', 'is_visibility_name', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
    }

    public function down()
    {

    }
}
