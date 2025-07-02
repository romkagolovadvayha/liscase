<?php

use console\components\migration\Migration;

class m250324_081040_lk_917_add_column_to_task_tbl extends Migration
{
    public function up()
    {
        $this->addColumn('tasks', 'lk_lang', self::VARCHAR_FIELD);
    }

    public function down()
    {

    }
}
