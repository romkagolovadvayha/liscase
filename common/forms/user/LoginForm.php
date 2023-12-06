<?php

namespace common\forms\user;

use Yii;
use common\components\base\Model;
use common\components\web\Cookie;
use common\models\user\User;

class LoginForm extends Model
{
    public $email;
    public $password;
    public $rememberMe = true;

    public function attributeLabels()
    {
        return [
            'email'      => Yii::t('common', 'E-mail'),
            'password'   => Yii::t('common', 'Пароль'),
            'rememberMe' => Yii::t('common', 'Запомнить меня'),
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
            ['rememberMe', 'safe'],
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
                if ($user->status == User::STATUS_BLOCKED) {
                    $this->addError($attribute, Yii::t('common', 'Аккаунт заблокирован'));

                } elseif ($user->status == User::STATUS_TMP_BLOCKED) {
                    $this->addError($attribute,
                        Yii::t('common', 'Аккаунт заблокирован. Обратитесь в поддержу') . ' support@digiu.ai');
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
        return User::findByEmail($this->email);
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

        if ($this->rememberMe) {
            Cookie::add('currentEmail', $this->email);
        } else {
            Cookie::remove('currentEmail');
        }

        $user->updateCurrentLanguage();

        return Yii::$app->user->login($user, 0);
    }

    /**
     * @return User|false
     */
    public function loginTelegram()
    {
        if (!$this->validate()) {
            return false;
        }

        return $this->_getUser();
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public static function loginWithoutValidate($user)
    {
        if (empty($user)) {
            return false;
        }

        if (!Yii::$app->user->isGuest) {
            return true;
        }

        $user->updateCurrentLanguage();

        return Yii::$app->user->login($user, 0);
    }
}