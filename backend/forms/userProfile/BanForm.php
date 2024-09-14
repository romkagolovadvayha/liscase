<?php

namespace backend\forms\userProfile;

use common\forms\profit\PersonalBalanceBonusForm;
use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\user\UserPayoutReferral;
use Yii;
use yii\base\BaseObject;
use yii\base\Model;

class BanForm extends Model
{
    public $reason;

    /**
     * @var User
     */
    public $user;

    public function attributeLabels()
    {
        return [
            'reason' => 'Причина бана',
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
        return $this->user->ban($this->reason, Yii::$app->user->id);
    }
}
