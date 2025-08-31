<?php

use console\components\migration\Migration;

/**
 * Class m250831_182233_rate_user
 */
class m250831_182233_rate_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("ALTER TABLE `user` ADD COLUMN `floating_price_percent` INT(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Максимальный процент колебания цены';");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250831_182233_rate_user cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250831_182233_rate_user cannot be reverted.\n";

        return false;
    }
    */
}
