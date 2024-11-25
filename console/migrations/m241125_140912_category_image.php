<?php

use console\components\migration\Migration;

/**
 * Class m241125_140912_category_image
 */
class m241125_140912_category_image extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('category','image', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241125_140912_category_image cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241125_140912_category_image cannot be reverted.\n";

        return false;
    }
    */
}
