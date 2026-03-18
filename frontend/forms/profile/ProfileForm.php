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
    public $twitch_disabled;
    public $kick_disabled;

    public function rules(): array
    {
        $linkAttrs = ['trade_link', 'youtube_link', 'twitch_link', 'vk_link', 'telegram_link'];
        if ($this->hasAttribute('kick_link')) {
            $linkAttrs[] = 'kick_link';
        }
        $rules = [
            [$linkAttrs, 'trim'],
            [['raid_notify', 'ban_notify', 'telegram_disabled', 'discord_disabled', 'twitch_disabled', 'kick_disabled'], 'integer'],
            [['is_hide_online', 'is_hide_team'], 'boolean'],
            [$linkAttrs, 'string', 'max' => 255],
            [['youtube_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'enableClientValidation' => false],
            [['twitch_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'enableClientValidation' => false],
            [['vk_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'enableClientValidation' => false],
            [['telegram_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'enableClientValidation' => false],
            [['is_hide_online', 'is_hide_team'], 'safe'],
        ];
        if ($this->hasAttribute('kick_link')) {
            $rules[] = [['kick_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'enableClientValidation' => false];
        }
        return $rules;
    }

    public function afterFind()
    {
        $this->ban_notify = $this->user->ban_notify;
        $this->raid_notify = $this->user->raid_notify;
        $this->is_hide_online = $this->is_hide_online ?? 0;
        $this->is_hide_team = $this->is_hide_team ?? 0;
        parent::afterFind();
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        // Обрабатываем пустые строки для URL полей перед валидацией
        if ($this->youtube_link === '') {
            $this->youtube_link = null;
        }
        if ($this->twitch_link === '') {
            $this->twitch_link = null;
        }
        if ($this->hasAttribute('kick_link') && $this->kick_link === '') {
            $this->kick_link = null;
        }
        if ($this->vk_link === '') {
            $this->vk_link = null;
        }
        if ($this->telegram_link === '') {
            $this->telegram_link = null;
        }
        
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

        if (!empty($this->twitch_disabled)) {
            $this->user->twitch_id = null;
            $this->twitch_link = null;
        }

        if (!empty($this->kick_disabled)) {
            $this->user->kick_id = null;
            if ($this->hasAttribute('kick_link')) {
                $this->kick_link = null;
            }
        }

        // Настройки приватности (только для VIP)
        // Значения уже должны быть установлены из POST, просто проверяем VIP
        if (!$this->user->hasVip()) {
            // Если нет VIP, сбрасываем флаги
            $this->is_hide_online = false;
            $this->is_hide_team = false;
        } else {
            // Для VIP пользователей просто убеждаемся, что значения корректны (boolean)
            $this->is_hide_online = (bool)$this->is_hide_online;
            $this->is_hide_team = (bool)$this->is_hide_team;
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

        // Сохранение социальных ссылок (так же, как trade_link)
        $this->youtube_link = !empty($this->youtube_link) ? trim($this->youtube_link) : null;
        $this->twitch_link = !empty($this->twitch_link) ? trim($this->twitch_link) : null;
        if ($this->hasAttribute('kick_link')) {
            $this->kick_link = !empty($this->kick_link) ? trim($this->kick_link) : null;
        }
        $this->vk_link = !empty($this->vk_link) ? trim($this->vk_link) : null;
        $this->telegram_link = !empty($this->telegram_link) ? trim($this->telegram_link) : null;
        
        if (!$this->save()) {
            Yii::error('Failed to save UserProfile: ' . json_encode($this->getErrors()));
            Yii::error('UserProfile attributes: ' . json_encode($this->attributes));
            throw new \Exception('UserProfile not saved: ' . json_encode($this->getErrors()));
        }
        
        if (!$this->user->save()) {
            Yii::error('Failed to save User: ' . json_encode($this->user->getErrors()));
            throw new \Exception('User not saved: ' . json_encode($this->user->getErrors()));
        }
        Yii::info('User saved successfully');
        return true;
    }

}
