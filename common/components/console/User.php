<?php

namespace common\components\console;

use Yii;

/**
 * @inheritdoc
 *
 * @property \common\models\user\User|\yii\web\IdentityInterface|null $identity
 */
class User extends \yii\web\User
{
    public function getId()
    {
        return 10;
    }

    public function getIdentity($autoRenew = true)
    {
        return \common\models\user\User::findOne($this->getId());
    }
}