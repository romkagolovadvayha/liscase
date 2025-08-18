<?php

use yii\db\Migration;

/**
 * Class m250715_195748_video_settings
 */
class m250715_195748_video_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("ALTER TABLE {{%site_settings}} CHANGE COLUMN `type` `type` ENUM('text', 'color', 'video', 'file', 'image', 'number', 'checkbox', 'radio', 'longtext') NOT NULL");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250715_195748_video_settings cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250715_195748_video_settings cannot be reverted.\n";

        return false;
    }
    */
}
