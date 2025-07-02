<?php

use console\components\migration\Migration;

class m250317_022833_desctiprion extends Migration
{
    public function up()
    {
        $this->addColumn('tasks_publish_place', 'description', 'VARCHAR(512) DEFAULT NULL');
    }

    public function down()
    {

    }
}
