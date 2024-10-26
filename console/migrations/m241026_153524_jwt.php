<?php

use yii\db\Migration;

/**
 * Class m241026_153524_jwt
 */
class m241026_153524_jwt extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','jwt', 'VARCHAR(32) DEFAULT NULL AFTER status');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241026_153524_jwt cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241026_153524_jwt cannot be reverted.\n";

        return false;
    }
    */
}
