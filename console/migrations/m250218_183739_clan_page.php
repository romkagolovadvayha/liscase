<?php

use yii\db\Migration;

/**
 * Class m250218_183739_clan_page
 */
class m250218_183739_clan_page extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addForeignKey('clan_page_user_id', 'clan_page', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_page_clan_id', 'clan_page', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250218_183739_clan_page cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250218_183739_clan_page cannot be reverted.\n";

        return false;
    }
    */
}
