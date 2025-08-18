<?php

use console\components\migration\Migration;

class m221114_203400_single_tree extends Migration
{
    public function up()
    {
        $this->createTable('user_tree', [
            'id'             => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'parent_user_id' => self::INT_FIELD,
            'lft'            => self::INT_FIELD_NOT_NULL,
            'rgt'            => self::INT_FIELD_NOT_NULL,
            'level'          => self::TINYINT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);

        $this->createIndex('index_lft', 'user_tree', ['lft', 'rgt']);
        $this->createIndex('index_rgt', 'user_tree', ['rgt']);

        $this->addForeignKey('fk_user_tree_user_id', 'user_tree', 'user_id',
            'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_user_tree_parent_user_id', 'user_tree', 'parent_user_id',
            'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function down()
    {

    }
}
