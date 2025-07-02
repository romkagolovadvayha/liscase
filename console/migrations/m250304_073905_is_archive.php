<?php

use console\components\migration\Migration;

class m250304_073905_is_archive extends Migration
{
    public function up()
    {
        $this->addColumn('tasks', 'is_archive', self::TINYINT_1_FIELD);
    }

    public function down()
    {

    }
}
