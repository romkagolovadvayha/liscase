<?php

namespace frontend\forms\profile;

use common\models\skindrops\Skindrops;
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

        $inventoryClosed = false;
        try {
            $apiUrl = "https://steamcommunity.com/inventory/{$this->user->steam_id}/252490/2";
            $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
            if ($response['success'] !== 1) {
                $inventoryClosed = true;
            }
        } catch (\Exception $ex) {
            $inventoryClosed = true;
        }
        if ($inventoryClosed) {
            $this->addError('trade_link', Yii::t('common', 'Ваш инвентарь скрыт или не доступен!'));
            return false;
        }
        if (strpos($this->trade_link, 'steamcommunity.com') === false) {
            $this->addError('trade_link', Yii::t('common', 'Ссылка на обмен указана неверно!'));
            return false;
        }
        if (strpos($this->trade_link, 'steamcommunity.com') === false) {
            $this->addError('trade_link', Yii::t('common', 'Ссылка на обмен указана неверно!'));
            return false;
        }
        $partner = Skindrops::getUrlQuery($this->trade_link, 'partner');
        $token = Skindrops::getUrlQuery($this->trade_link, 'token');
        if (empty($partner) || empty($token)) {
            $this->addError('trade_link', Yii::t('common', 'Ссылка на обмен указана неверно!'));
            return false;
        }
        $this->skindrops = 1;
        $this->skindrops_error = null;
        if (!$this->save()) {
            throw new \Exception('User not saved');
        }

        return true;
    }

}
