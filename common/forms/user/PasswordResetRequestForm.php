<?php

namespace common\forms\user;

use Yii;
use yii\base\Model;
use common\components\mail\event\Mailer;
use common\models\user\User;
use common\models\user\UserConfirmCode;

class PasswordResetRequestForm extends Model
{
    public $email;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['email', 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            [
                'email',
                'exist',
                'targetClass' => User::class,
                'filter'      => ['status' => User::STATUS_ACTIVE],
                'message'     => Yii::t('common', 'Указанный Email не найден'),
            ],
        ];
    }

    public function attributeLabels()
    {
        return [
            'email'        => Yii::t('common', 'E-mail'),
        ];
    }

    /**
     * Sends an email with a link, for resetting the password.
     *
     * @return bool whether the email was send
     */
    public function sendEmail()
    {
        /* @var $user User */
        $user = User::findOne([
            'status' => User::STATUS_ACTIVE,
            'email'  => $this->email,
        ]);

        if (!$user) {
            return false;
        }

        if (!UserConfirmCode::createTypePassword($user->id)) {
            return false;
        }

        return Mailer::getInstance($user)->sendPasswordReset();
    }
}
