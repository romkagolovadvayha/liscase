<?php

namespace frontend\forms\profile;

use common\models\user\UserProfile;
use Yii;

class ProfileForm extends UserProfile
{

    public function rules(): array
    {
        return [
            [['trade_link'], 'required'],
            [['trade_link'], 'trim'],
            [['trade_link'], 'string', 'max' => 255],
        ];
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if (strpos($this->trade_link, 'steamcommunity.com') === false) {
            $this->addError('trade_link', Yii::t('common', 'Ссылка на обмен указана неверно!'));
            return false;
        }

        if (!$this->save()) {
            throw new \Exception('User not saved');
        }

        return true;
    }

}
