<?php

use console\components\migration\Migration;

/**
 * Class m241227_185306_cases
 */
class m241227_185306_cases extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('category','show_main_block', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
        $this->addColumn('sets','show_main_block', self::TINYINT_1_FIELD);
        $this->addColumn('drop','show_main_block', self::TINYINT_1_FIELD);
        $this->addColumn('select','show_main_block', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        echo "m241227_185306_cases cannot be reverted.\n";
//
//        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241227_185306_cases cannot be reverted.\n";

        return false;
    }
    */
}
