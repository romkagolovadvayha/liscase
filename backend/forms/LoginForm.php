<?php

namespace backend\forms;

use Yii;
use common\components\base\Model;
use common\models\user\User;

class LoginForm extends Model
{
    public $email;
    public $password;

    public function attributeLabels()
    {
        return [
            'email'    => Yii::t('common', 'Email'),
            'password' => Yii::t('common', 'Пароль'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['email', 'password'], 'trim'],
            [['email', 'password'], 'required'],
            [['email'], 'email'],
            ['password', 'validatePassword'],
        ];
    }

    /**
     * @param $attribute
     */
    public function validatePassword($attribute)
    {
        if (!$this->hasErrors()) {
            $user = $this->_getUser();
            if ($user && $user->validatePassword($this->password)) {
                if ($user->status == User::STATUS_BLOCKED || $user->status == User::STATUS_TMP_BLOCKED) {
                    $this->addError($attribute, Yii::t('common', 'Аккаунт заблокирован'));
                }

            } else {
                $this->addError($attribute, Yii::t('common', 'Неверный логин или пароль'));
            }
        }
    }

    /**
     * @return User|null
     */
    private function _getUser()
    {
        $user = User::findByEmail($this->email);
        if (empty($user)) {
            return null;
        }

        if (!$user->isAccessBackend()) {
            return null;
        }

        return $user;
    }

    /**
     * @return bool|User
     */
    public function login()
    {
        if (!$this->validate()) {
            return false;
        }

        $user = $this->_getUser();

        return Yii::$app->user->login($user, 0);
    }
}