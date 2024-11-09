<?php

use yii\db\Migration;

/**
 * Class m241109_092633_user_is_gaimer
 */
class m241109_092633_user_is_gaimer extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','is_gamer', 'TINYINT(3) UNSIGNED DEFAULT 0 AFTER rustru_scrap_wait');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241109_092633_user_is_gaimer cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241109_092633_user_is_gaimer cannot be reverted.\n";

        return false;
    }
    */
}
