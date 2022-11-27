<?php

use console\components\migration\Migration;

class m221114_211902_init_keys extends Migration
{
    public function up()
    {
        $this->addForeignKey('fk_box_drop_drop_id', 'box_drop', 'drop_id',
            'drop', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_box_drop_box_id', 'box_drop', 'box_id',
            'box', 'id', 'SET NULL', 'CASCADE');

        $this->addForeignKey('fk_profit_user_balance_id', 'profit', 'user_balance_id',
            'user_balance', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('fk_user_balance_user_id', 'user_balance', 'user_id',
            'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function down()
    {

    }
}
