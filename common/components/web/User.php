<?php

namespace common\components\web;

use Yii;
use wealth\models\WealthUser;

/**
 * @inheritdoc
 *
 * @property \common\models\user\User|\yii\web\IdentityInterface|null $identity
 */
class User extends \yii\web\User
{
    /**
     * @return string
     */
    public function getSocketRoom()
    {
        return !$this->isGuest ? $this->identity->socket_room : null;
    }

    /**
     * @param bool $isShort
     *
     * @return string
     */
    public function getLevelDescription($isShort = false)
    {
        return $this->identity->userInvestor->getLevelDescription($isShort);
    }

    /**
     * @return int|null
     */
    public function getWealthUserId()
    {
        if ($this->isGuest) {
            return null;
        }

        $wealthUser = WealthUser::getModel($this->id, true);

        return $wealthUser ? $wealthUser->id : null;
    }
}