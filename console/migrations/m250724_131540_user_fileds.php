<?php

use yii\db\Migration;

/**
 * Class m250724_131540_user_fileds
 */
class m250724_131540_user_fileds extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $usersQuery = \common\models\user\User::find();

        /** @var \common\models\user\User[] $users */
        foreach ($usersQuery->batch(1000) as $users) {
            foreach ($users as $user) {
                $user->createXfUserPrivacy();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250724_131540_user_fileds cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250724_131540_user_fileds cannot be reverted.\n";

        return false;
    }
    */
}
