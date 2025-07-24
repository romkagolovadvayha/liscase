<?php

use yii\db\Migration;

/**
 * Class m250724_122925_user_fileds
 */
class m250724_122925_user_fileds extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'username_date', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'username_date_visible', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'custom_title', $this->string(50)->notNull());
        $this->addColumn('user', 'language_id', $this->integer()->unsigned()->notNull()->defaultValue(2));
        $this->addColumn('user', 'style_id', $this->integer()->unsigned()->notNull());
        $this->addColumn('user', 'timezone', $this->string(50)->notNull()->defaultValue('Europe/Moscow'));
        $this->addColumn('user', 'visible', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(1));
        $this->addColumn('user', 'activity_visible', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(1));
        $this->addColumn('user', 'user_group_id', $this->integer()->unsigned()->notNull()->defaultValue(2));
        $this->addColumn('user', 'secondary_group_ids', $this->binary(255)->notNull());
        $this->addColumn('user', 'display_style_group_id', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'permission_combination_id', $this->integer()->unsigned()->notNull());
        $this->addColumn('user', 'message_count', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'question_solution_count', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'conversations_unread', $this->smallInteger(5)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'register_date', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'last_activity', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'last_summary_email_date', $this->integer()->unsigned()->defaultValue(null));
        $this->addColumn('user', 'trophy_points', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'alerts_unviewed', $this->smallInteger(5)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'alerts_unread', $this->smallInteger(5)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'avatar_date', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'avatar_width', $this->smallInteger(5)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'avatar_height', $this->smallInteger(5)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'avatar_highdpi', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'gravatar', $this->string(120));
        $this->addColumn('user', 'user_state', "ENUM('valid', 'email_confirm', 'email_confirm_edit') NOT NULL DEFAULT 'valid'");
        $this->addColumn('user', 'security_lock', "ENUM('', 'change', 'reset') NOT NULL");
        $this->addColumn('user', 'is_moderator', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'is_admin', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'is_banned', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'reaction_score', $this->integer()->notNull()->defaultValue(0));
        $this->addColumn('user', 'vote_score', $this->integer()->notNull()->defaultValue(0));
        $this->addColumn('user', 'warning_points', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'is_staff', $this->tinyInteger(3)->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'secret_key', $this->binary(32)->notNull());
        $this->addColumn('user', 'privacy_policy_accepted', $this->integer()->unsigned()->notNull()->defaultValue(0));
        $this->addColumn('user', 'terms_accepted', $this->integer()->unsigned()->notNull()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250724_122925_user_fileds cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250724_122925_user_fileds cannot be reverted.\n";

        return false;
    }
    */
}
