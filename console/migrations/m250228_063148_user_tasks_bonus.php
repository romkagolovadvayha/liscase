<?php

use console\components\migration\Migration;

class m250228_063148_user_tasks_bonus extends Migration
{
    public function up()
    {
        $this->addColumn('user_tasks', 'amount', 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down()
    {

    }
}
