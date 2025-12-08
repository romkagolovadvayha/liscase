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
    public $is_hide_online;
    public $is_hide_team;

    public function rules(): array
    {
        return [
            [['trade_link', 'youtube_link', 'twitch_link', 'vk_link', 'telegram_link'], 'trim'],
            [['raid_notify', 'ban_notify', 'telegram_disabled', 'discord_disabled', 'is_hide_online', 'is_hide_team'], 'integer'],
            [['trade_link', 'youtube_link', 'twitch_link', 'vk_link', 'telegram_link'], 'string', 'max' => 255],
            [['youtube_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'when' => function($model) {
                return !empty($model->youtube_link);
            }],
            [['twitch_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'when' => function($model) {
                return !empty($model->twitch_link);
            }],
            [['vk_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'when' => function($model) {
                return !empty($model->vk_link);
            }],
            [['telegram_link'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true, 'when' => function($model) {
                return !empty($model->telegram_link);
            }],
        ];
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
        // Загружаем данные из POST, если они есть
        $post = Yii::$app->request->post('ProfileForm', []);
        if (!empty($post)) {
            if (isset($post['youtube_link'])) {
                $this->youtube_link = $post['youtube_link'];
            }
            if (isset($post['twitch_link'])) {
                $this->twitch_link = $post['twitch_link'];
            }
            if (isset($post['vk_link'])) {
                $this->vk_link = $post['vk_link'];
            }
            if (isset($post['telegram_link'])) {
                $this->telegram_link = $post['telegram_link'];
            }
            if (isset($post['is_hide_online'])) {
                $this->is_hide_online = $post['is_hide_online'];
            }
            if (isset($post['is_hide_team'])) {
                $this->is_hide_team = $post['is_hide_team'];
            }
        }
        
        if (!$this->validate()) {
            Yii::error('ProfileForm validation failed: ' . json_encode($this->getErrors()));
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

        // Сохранение социальных ссылок
        $this->youtube_link = !empty($this->youtube_link) ? trim($this->youtube_link) : null;
        $this->twitch_link = !empty($this->twitch_link) ? trim($this->twitch_link) : null;
        $this->vk_link = !empty($this->vk_link) ? trim($this->vk_link) : null;
        $this->telegram_link = !empty($this->telegram_link) ? trim($this->telegram_link) : null;

        // Настройки приватности (только для VIP)
        if ($this->user->hasVip()) {
            $this->is_hide_online = !empty($this->is_hide_online) ? 1 : 0;
            $this->is_hide_team = !empty($this->is_hide_team) ? 1 : 0;
        } else {
            // Если нет VIP, сбрасываем флаги
            $this->is_hide_online = 0;
            $this->is_hide_team = 0;
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
