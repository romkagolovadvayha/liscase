<?php

use yii\db\Migration;

/**
 * Class m250730_180223_drop_proce_fix
 */
class m250730_180223_drop_proce_fix extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("ALTER TABLE `drop` ADD COLUMN `floating_price_percent` INT(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Максимальный процент колебания цены';");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250730_180223_drop_proce_fix cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250730_180223_drop_proce_fix cannot be reverted.\n";

        return false;
    }
    */
}
