<?php

use yii\db\Migration;

/**
 * Class m240623_144453_referral_bonus
 */
class m240623_144453_referral_bonus extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_profile','referral_bonus', 'INT(11) DEFAULT 20 AFTER skindrops_error');
        $this->addColumn('user_profile','referral_click', 'INT(11) DEFAULT 0 AFTER referral_bonus');
        $this->addColumn('user_profile','blogger_account', 'VARCHAR(255) DEFAULT NULL AFTER referral_click');
        $this->addColumn('user_profile','is_blogger', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER blogger_account');
        $this->addColumn('user_profile','parent_bonus', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER blogger_account');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240623_144453_referral_bonus cannot be reverted.\n";

        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240623_144453_referral_bonus cannot be reverted.\n";

        return false;
    }
    */
}
