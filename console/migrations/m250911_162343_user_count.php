<?php

use console\components\migration\Migration;

/**
 * Class m250911_162343_user_count
 */
class m250911_162343_user_count extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('clan_stats','scrap', self::INT_FIELD);
        $this->addColumn('clan_stats','sulfur_ore', self::INT_FIELD);
        $this->addColumn('clan_stats','helicopter', self::INT_FIELD);
        $this->addColumn('clan_stats','tugboat', self::INT_FIELD);
        $this->addColumn('clan_stats','bradley', self::INT_FIELD);
        $this->addColumn('user_clan_stats','clan_id', self::INT_FIELD);
        $this->addForeignKey('user_clan_stats_clan_id', 'user_clan_stats', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250911_162343_user_count cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250911_162343_user_count cannot be reverted.\n";

        return false;
    }
    */
}
