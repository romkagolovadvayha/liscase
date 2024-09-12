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

class SkinForm extends Model
{
    public $amount;

    /**
     * @var User
     */
    public $user;

    public function attributeLabels()
    {
        return [
            'amount' => 'Сумма',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['amount'], 'trim'],
            [['amount', 'user'], 'required'],
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
        $form = new UserPayoutReferral();
        $form->user_id  = $this->user->id;
        $form->amount  = $this->amount;
        $form->created_at = date('Y-m-d H:i:s');
        return $form->save();
    }
}
