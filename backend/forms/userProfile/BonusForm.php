<?php

namespace backend\forms\userProfile;

use common\forms\profit\PersonalBalanceBonusForm;
use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use Yii;
use yii\base\BaseObject;
use yii\base\Model;

class BonusForm extends Model
{
    public $amount;
    public $type_balance;

    /**
     * @var UserBalance
     */
    private $_balance;

    /**
     * @var User
     */
    public $user;

    public function attributeLabels()
    {
        return [
            'amount' => 'Бонус',
            'type_balance' => 'Счет',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['amount'], 'trim'],
            [['type_balance'], 'trim'],
            [['amount', 'type_balance', 'user'], 'required'],
            [['type_balance'], 'validateTypeBalance'],
        ];
    }

    public function setUserId($userId)
    {
        if (empty($userId)) {
            return;
        }
        $this->user = User::findOne($userId);
        $this->type_balance = UserBalance::TYPE_PERSONAL;
        if (empty($this->user)) {
            $this->addError('formError', 'Пользователь не найден');
        }
    }

    public function validateTypeBalance()
    {
        $this->_balance = UserBalance::getBalance($this->user->id, $this->type_balance);
        if (empty($this->_balance)) {
            $this->addError('formError', 'Счет пользователя не найден!');
        }
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (YII_ENV_PROD) {
            return false;
        }
        if (!$this->validate()) {
            return false;
        }
        $form = new Profit();
        $form->user_balance_id  = $this->_balance->id;
        $form->amount  = $this->amount;
        $form->type  = Profit::TYPE_BONUS;
        $form->comment = Yii::t('common', 'Начисление бонуса', [], 'ru-RU');
        $form->status = 1;
        $form->created_at = date('Y-m-d H:i:s');
        return $form->save();
    }
}
