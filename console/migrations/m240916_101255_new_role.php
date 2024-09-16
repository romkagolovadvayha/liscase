<?php

use yii\db\Migration;

/**
 * Class m240916_101255_new_role
 */
class m240916_101255_new_role extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $role = Yii::$app->authManager->createRole(\common\components\helpers\Role::ROLE_MODERATOR);
        $role->description = Yii::t('common', 'Модератор');
        Yii::$app->authManager->add($role);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240916_101255_new_role cannot be reverted.\n";

        return false;
    }
    */
}
