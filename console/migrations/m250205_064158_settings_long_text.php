<?php

use yii\db\Migration;

/**
 * Class m250205_064158_settings_long_text
 */
class m250205_064158_settings_long_text extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("ALTER TABLE {{%site_settings}} CHANGE COLUMN `type` `type` ENUM('text', 'color', 'file', 'image', 'number', 'checkbox', 'radio', 'longtext') NOT NULL");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250205_064158_settings_long_text cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250205_064158_settings_long_text cannot be reverted.\n";

        return false;
    }
    */
}
