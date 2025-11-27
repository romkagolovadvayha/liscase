<?php

namespace frontend\forms\profile;

use common\components\helpers\DateHelper;
use common\models\skindrops\Skindrops;
use common\models\user\UserProfile;
use Yii;

class ProfileForm extends UserProfile
{
    public $ban_notify;
    public $raid_notify;
    public $telegram_disabled;
    public $discord_disabled;

    public function rules(): array
    {
        return [
            [['trade_link'], 'trim'],
            [['raid_notify', 'ban_notify', 'telegram_disabled', 'discord_disabled'], 'integer'],
            [['trade_link'], 'string', 'max' => 255],
        ];
    }

    public function afterFind()
    {
        $this->ban_notify = $this->user->ban_notify;
        $this->raid_notify = $this->user->raid_notify;
        parent::afterFind();
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $cacheKey = "ProfileForm_{$this->id}";
        if (Yii::$app->cache->get($cacheKey)) {
            $seconds = Yii::$app->cache->get($cacheKey) - time();
            $secondsWord = DateHelper::numDecline($seconds, 'секунда, секунды, секунд', false);
            $this->addError('global', Yii::t('common', "Вы делаете запросы слишком часто, попробуйте через {PARAM_SECOND} {PARAM_SECOND_WORD}.", [
                'PARAM_SECOND' => $seconds,
                'PARAM_SECOND_WORD' => $secondsWord,
            ]));
            //return false;
        }
        Yii::$app->cache->set($cacheKey, time() + 5, 5);

        if ($this->raid_notify != $this->user->raid_notify) {
            $this->user->raid_notify = $this->raid_notify;
        }
        if ($this->ban_notify != $this->user->ban_notify) {
            $this->user->ban_notify = $this->ban_notify;
        }

        if (!empty($this->telegram_disabled)) {
            $this->user->telegram_chat_id = null;
        }

        if (!empty($this->discord_disabled)) {
            // Сохраняем discord_id перед обнулением для удаления роли
            $discordId = $this->user->discord_id;
            $this->user->discord_id = null;
            
            // Удаляем роль в Discord, если была привязана
            if (!empty($discordId)) {
                \common\controllers\AuthController::removeDiscordRole($discordId);
            }
        }

        $this->skindrops = 0;
        if (!empty($this->trade_link)) {
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
        }

        if (!$this->save() || !$this->user->save()) {
            throw new \Exception('User not saved');
        }

        return true;
    }

}
