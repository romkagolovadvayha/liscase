<?php

use yii\db\Migration;

/**
 * Class m241026_114419_suppurt_update_at
 */
class m241026_114419_suppurt_update_at extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('support','updated_at', 'TIMESTAMP NULL AFTER status');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241026_114419_suppurt_update_at cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241026_114419_suppurt_update_at cannot be reverted.\n";

        return false;
    }
    */
}
