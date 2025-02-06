<?php

use yii\db\Migration;

/**
 * Class m250206_160355_ban_notify
 */
class m250206_160355_ban_notify extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','ban_notify', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250206_160355_ban_notify cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250206_160355_ban_notify cannot be reverted.\n";

        return false;
    }
    */
}
