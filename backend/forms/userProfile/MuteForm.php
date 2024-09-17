<?php

namespace backend\forms\userProfile;

use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\user\UserPayoutReferral;
use Yii;
use yii\base\BaseObject;
use yii\base\Model;

class MuteForm extends Model
{
    public $reason;

    /**
     * @var User
     */
    public $user;

    public function attributeLabels()
    {
        return [
            'reason' => 'Причина мута',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['reason'], 'integer'],
            [['reason', 'user'], 'required'],
        ];
    }

    public function setUserId($userId)
    {
        if (empty($userId)) {
            return;
        }
        $this->user = User::findOne($userId);
        if (empty($this->user)) {
            $this->addError('formError', 'Пользователь не найден');
        }
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        return $this->user->mute($this->reason);
    }
}
