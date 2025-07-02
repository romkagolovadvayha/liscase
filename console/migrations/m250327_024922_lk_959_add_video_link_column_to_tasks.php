<?php

use console\components\migration\Migration;

class m250327_024922_lk_959_add_video_link_column_to_tasks extends Migration
{
    public function up()
    {
        $this->addColumn('tasks', 'video_link', self::VARCHAR_FIELD);
    }

    public function down()
    {

    }
}
