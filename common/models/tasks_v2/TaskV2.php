<?php

namespace common\models\tasks_v2;

use common\components\base\ActiveRecord;
use common\models\battle_pass\BattlePassSeason;
use common\models\box\Drop;
use common\models\user\User;
use Yii;
use yii\helpers\Json;

/**
 * This is the model class for table "tasks_v2".
 *
 * @property int $id
 * @property string $title
 * @property string|null $short_description
 * @property string|null $full_description
 * @property string $type
 * @property int|null $battle_pass_season_id
 * @property int|null $battle_pass_position
 * @property string $check_type
 * @property string|null $check_params
 * @property string $reward_type
 * @property int|null $reward_item_id
 * @property string|null $reward_currency
 * @property float|null $reward_amount
 * @property int|null $per_user_limit
 * @property int|null $global_limit
 * @property int $global_completed
 * @property int|null $max_progress
 * @property string|null $image_path
 * @property string $button_text
 * @property string|null $extra_buttons
 * @property int $is_active
 * @property int $is_visible_for_guests
 * @property int $is_vip_only
 * @property string|null $available_from
 * @property int $sort
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property Drop $rewardItem
 * @property TaskV2UserCompletion[] $userCompletions
 */
class TaskV2 extends ActiveRecord
{
    const TYPE_ONE_TIME = 'one_time';
    const TYPE_REPEATABLE = 'repeatable';
    const TYPE_DAILY_REWARD = 'daily_reward';
    const TYPE_BATTLE_PASS = 'battle_pass';

    const REWARD_TYPE_ITEM = 'item';
    const REWARD_TYPE_CURRENCY = 'currency';

    const CHECK_TYPE_VK_SUBSCRIBE_GROUP = 'vk_subscribe_group';
    const CHECK_TYPE_TELEGRAM_CONNECTED = 'telegram_connected';
    const CHECK_TYPE_TELEGRAM_CHANNEL_SUBSCRIBE = 'telegram_channel_subscribe';
    const CHECK_TYPE_DISCORD_JOIN = 'discord_join';
    const CHECK_TYPE_KILL_BOTS_COUNT = 'kill_bots_count';
    const CHECK_TYPE_INVITE_FRIEND = 'invite_friend';
    const CHECK_TYPE_CUSTOM_MANUAL = 'custom_manual';
    const CHECK_TYPE_DAILY_REWARD = 'daily_reward';
    const CHECK_TYPE_STATISTICS_PARAM = 'statistics_param';
    const CHECK_TYPE_COMMENTS_COUNT = 'comments_count';
    const CHECK_TYPE_BUILDING_ADD = 'building_add';
    const CHECK_TYPE_RADIO_TRACK_ADD = 'radio_track_add';
    const CHECK_TYPE_SKIN_ADD = 'skin_add';
    const CHECK_TYPE_COBALTLAB_REGISTRATION = 'cobaltlab_registration';
    const CHECK_TYPE_COBALTLAB_FIRST_DEPOSIT = 'cobaltlab_first_deposit';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks_v2';
    }

    /**
     * Виртуальный атрибут для загрузки файла изображения
     * @var \yii\web\UploadedFile|null
     */
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'check_type', 'reward_type'], 'required'],
            [['short_description', 'full_description'], 'string'],
            [['type'], 'in', 'range' => [self::TYPE_ONE_TIME, self::TYPE_REPEATABLE, self::TYPE_DAILY_REWARD, self::TYPE_BATTLE_PASS]],
            [['reward_type'], 'in', 'range' => [self::REWARD_TYPE_ITEM, self::REWARD_TYPE_CURRENCY]],
            [['reward_item_id', 'battle_pass_season_id', 'battle_pass_position', 'per_user_limit', 'global_limit', 'global_completed', 'max_progress', 'is_active', 'is_visible_for_guests', 'is_vip_only', 'sort'], 'integer'],
            [['battle_pass_season_id', 'battle_pass_position'], 'required', 'when' => function ($model) {
                return $model->type === self::TYPE_BATTLE_PASS;
            }],
            [['battle_pass_season_id', 'battle_pass_position'], 'unique', 'targetAttribute' => ['battle_pass_season_id', 'battle_pass_position'], 'message' => Yii::t('common', 'Этот номер уже занят в выбранном сезоне.')],
            [['reward_amount'], 'number'],
            [['check_params', 'extra_buttons'], 'safe'],
            [['created_at', 'updated_at', 'available_from'], 'safe'],
            [['title'], 'string', 'max' => 255],
            [['check_type'], 'string', 'max' => 100],
            [['reward_currency'], 'string', 'max' => 50],
            [['image_path'], 'string', 'max' => 255],
            [['button_text'], 'string', 'max' => 100],
            [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, gif', 'maxSize' => 5 * 1024 * 1024],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => Yii::t('common', 'Название задания'),
            'short_description' => Yii::t('common', 'Краткое описание'),
            'full_description' => Yii::t('common', 'Полное описание'),
            'type' => Yii::t('common', 'Тип задания'),
            'battle_pass_season_id' => Yii::t('common', 'Сезон Battle Pass'),
            'battle_pass_position' => Yii::t('common', 'Номер в сезоне'),
            'check_type' => Yii::t('common', 'Тип проверки'),
            'check_params' => Yii::t('common', 'Параметры проверки'),
            'reward_type' => Yii::t('common', 'Тип награды'),
            'reward_item_id' => Yii::t('common', 'ID товара'),
            'reward_currency' => Yii::t('common', 'Тип баланса'),
            'reward_amount' => Yii::t('common', 'Сумма награды'),
            'per_user_limit' => Yii::t('common', 'Лимит на пользователя'),
            'global_limit' => Yii::t('common', 'Общий лимит'),
            'global_completed' => Yii::t('common', 'Выполнено раз'),
            'max_progress' => Yii::t('common', 'Максимальный прогресс'),
            'image_path' => Yii::t('common', 'Изображение'),
            'button_text' => Yii::t('common', 'Текст кнопки'),
            'extra_buttons' => Yii::t('common', 'Дополнительные кнопки'),
            'is_active' => Yii::t('common', 'Активно'),
            'is_visible_for_guests' => Yii::t('common', 'Видимо для гостей'),
            'is_vip_only' => Yii::t('common', 'Только для VIP'),
            'available_from' => Yii::t('common', 'Доступно с'),
            'sort' => Yii::t('common', 'Сортировка'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
            'imageFile' => Yii::t('common', 'Изображение'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        // Инвалидируем кэш списка заданий
        $cache = Yii::$app->cache;
        // Удаляем все возможные комбинации кэша заданий
        $cache->delete('api_tasks_list_' . md5(''));
        $cache->delete('api_tasks_list_' . md5('one_time_'));
        $cache->delete('api_tasks_list_' . md5('repeatable_'));
        $cache->delete('api_tasks_list_' . md5('_popularity'));
        $cache->delete('api_tasks_list_' . md5('_reward'));
        $cache->delete('api_tasks_list_' . md5('_newest'));
        $cache->delete('api_tasks_list_' . md5('one_time_popularity'));
        $cache->delete('api_tasks_list_' . md5('one_time_reward'));
        $cache->delete('api_tasks_list_' . md5('one_time_newest'));
        $cache->delete('api_tasks_list_' . md5('repeatable_popularity'));
        $cache->delete('api_tasks_list_' . md5('repeatable_reward'));
        $cache->delete('api_tasks_list_' . md5('repeatable_newest'));
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        parent::afterDelete();
        
        // Инвалидируем кэш списка заданий
        $cache = Yii::$app->cache;
        // Удаляем все возможные комбинации кэша заданий
        $cache->delete('api_tasks_list_' . md5(''));
        $cache->delete('api_tasks_list_' . md5('one_time_'));
        $cache->delete('api_tasks_list_' . md5('repeatable_'));
        $cache->delete('api_tasks_list_' . md5('_popularity'));
        $cache->delete('api_tasks_list_' . md5('_reward'));
        $cache->delete('api_tasks_list_' . md5('_newest'));
        $cache->delete('api_tasks_list_' . md5('one_time_popularity'));
        $cache->delete('api_tasks_list_' . md5('one_time_reward'));
        $cache->delete('api_tasks_list_' . md5('one_time_newest'));
        $cache->delete('api_tasks_list_' . md5('repeatable_popularity'));
        $cache->delete('api_tasks_list_' . md5('repeatable_reward'));
        $cache->delete('api_tasks_list_' . md5('repeatable_newest'));
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_at = date('Y-m-d H:i:s');
            }
            $this->updated_at = date('Y-m-d H:i:s');
            
            // Обработка available_from (конвертация из datetime-local в формат БД)
            if (!empty($this->available_from)) {
                // Если значение в формате datetime-local (Y-m-d\TH:i), конвертируем
                if (strpos($this->available_from, 'T') !== false) {
                    $this->available_from = date('Y-m-d H:i:s', strtotime($this->available_from));
                } elseif (strlen($this->available_from) === 16 && strpos($this->available_from, ' ') !== false) {
                    // Если формат Y-m-d H:i, добавляем секунды
                    $this->available_from = $this->available_from . ':00';
                } elseif (strtotime($this->available_from) !== false) {
                    // Если это валидная дата, форматируем
                    $this->available_from = date('Y-m-d H:i:s', strtotime($this->available_from));
                }
            } else {
                $this->available_from = null;
            }
            
            // Преобразуем массивы в JSON для сохранения только если они массивы
            if (is_array($this->check_params)) {
                $this->check_params = Json::encode($this->check_params);
            } elseif (empty($this->check_params)) {
                $this->check_params = null;
            }
            
            if (is_array($this->extra_buttons)) {
                // Фильтруем пустые значения
                $this->extra_buttons = array_filter($this->extra_buttons, function($button) {
                    return !empty($button['label']) && !empty($button['url']);
                });
                $this->extra_buttons = array_values($this->extra_buttons); // Переиндексируем
                if (empty($this->extra_buttons)) {
                    $this->extra_buttons = null;
                } else {
                    $this->extra_buttons = Json::encode($this->extra_buttons);
                }
            } elseif (empty($this->extra_buttons)) {
                $this->extra_buttons = null;
            }
            
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function afterFind()
    {
        parent::afterFind();
        
        // Преобразуем JSON в массивы
        if (!empty($this->check_params)) {
            try {
                $this->check_params = Json::decode($this->check_params);
            } catch (\Exception $e) {
                $this->check_params = [];
            }
        } else {
            $this->check_params = [];
        }
        
        if (!empty($this->extra_buttons)) {
            try {
                $this->extra_buttons = Json::decode($this->extra_buttons);
            } catch (\Exception $e) {
                $this->extra_buttons = [];
            }
        } else {
            $this->extra_buttons = [];
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRewardItem()
    {
        return $this->hasOne(Drop::class, ['id' => 'reward_item_id']);
    }

    public function getBattlePassSeason()
    {
        return $this->hasOne(BattlePassSeason::class, ['id' => 'battle_pass_season_id']);
    }

    /**
     * Награда-предмет для UI/API: не обращаться к {@see getRewardItem()} при пустом reward_item_id (иначе Yii даёт `drop WHERE 0=1`).
     * Дроп кэшируется 5 минут, сброс при правках в админке — {@see \common\models\box\Drop::invalidateApiRowCache()}.
     */
    public function getRewardDropCached(): ?Drop
    {
        if ($this->reward_type !== self::REWARD_TYPE_ITEM) {
            return null;
        }
        $id = (int)($this->reward_item_id ?? 0);
        if ($id <= 0) {
            return null;
        }
        return Drop::findOneCachedWithImageOrig($id);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserCompletions()
    {
        return $this->hasMany(TaskV2UserCompletion::class, ['task_id' => 'id']);
    }

    /**
     * Получить публичный URL изображения из S3
     * @return string
     */
    public function getImageUrl()
    {
        if (empty($this->image_path)) {
            return '/images/design/icons/128px/task-default.png';
        }
        
        return Yii::$app->settings->get('s3_publicUrl') . '/' . ltrim($this->image_path, '/');
    }

    /**
     * Получить статус задания для пользователя
     * @param User|null $user
     * @param array<int, TaskV2UserCompletion>|null $completionsByTaskId предзагрузка из API (один запрос); иначе null — чтение из БД по месту
     * @return array Статус: 'available', 'completed', 'limit_reached', 'unavailable'
     */
    public function getUserStatus($user, ?array $completionsByTaskId = null)
    {
        if (!$user || Yii::$app->user->isGuest) {
            if ($this->is_visible_for_guests) {
                return [
                    'status' => 'unavailable',
                    'message' => Yii::t('common', 'Требуется авторизация'),
                ];
            }
            return [
                'status' => 'hidden',
                'message' => '',
            ];
        }

        // Проверка даты доступности
        if ($this->available_from) {
            $availableFrom = strtotime($this->available_from);
            $now = time();
            if ($availableFrom > $now) {
                $availableFromDate = new \DateTime($this->available_from);
                return [
                    'status' => 'unavailable',
                    'message' => Yii::t('common', 'Доступно с {date}', [
                        'date' => $availableFromDate->format('d.m.Y H:i')
                    ]),
                    'available_from' => $this->available_from,
                ];
            }
        }

        // Проверка VIP статуса
        if ($this->is_vip_only) {
            $activeVip = \common\models\user\UserVip::getActiveVip($user->id);
            if (!$activeVip) {
                return [
                    'status' => 'unavailable',
                    'message' => Yii::t('common', 'Требуется VIP статус'),
                ];
            }
        }

        if ($completionsByTaskId !== null) {
            $completion = $completionsByTaskId[(int)$this->id] ?? null;
        } else {
            $completion = TaskV2UserCompletion::find()
                ->where(['task_id' => $this->id, 'user_id' => $user->id])
                ->one();
        }

        $countCompleted = $completion ? $completion->count_completed : 0;

        // Проверяем лимиты
        if ($this->per_user_limit !== null && $countCompleted >= $this->per_user_limit) {
            return [
                'status' => 'limit_reached',
                'message' => Yii::t('common', 'Лимит исчерпан'),
                'count_completed' => $countCompleted,
            ];
        }

        if ($this->global_limit !== null && $this->global_completed >= $this->global_limit) {
            return [
                'status' => 'unavailable',
                'message' => Yii::t('common', 'Задание выполнено всеми участниками'),
            ];
        }

        // Для ежедневных наград
        if ($this->type === self::TYPE_DAILY_REWARD && $this->check_type === self::CHECK_TYPE_DAILY_REWARD) {
            return $this->getDailyRewardStatus($user, $completion);
        }

        // Для одноразовых заданий
        if (in_array($this->type, [self::TYPE_ONE_TIME, self::TYPE_BATTLE_PASS], true) && $countCompleted > 0) {
            return [
                'status' => 'completed',
                'message' => Yii::t('common', 'Выполнено'),
                'count_completed' => $countCompleted,
            ];
        }

        return [
            'status' => 'available',
            'message' => Yii::t('common', 'Доступно'),
            'count_completed' => $countCompleted,
        ];
    }

    /**
     * Получить список типов заданий
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_ONE_TIME => Yii::t('common', 'Одноразовое'),
            self::TYPE_REPEATABLE => Yii::t('common', 'Многоразовое'),
            self::TYPE_DAILY_REWARD => Yii::t('common', 'Ежедневная награда'),
            self::TYPE_BATTLE_PASS => Yii::t('common', 'Battle Pass'),
        ];
    }

    /**
     * Получить список типов наград
     * @return array
     */
    public static function getRewardTypeList()
    {
        return [
            self::REWARD_TYPE_ITEM => Yii::t('common', 'Товар/предмет'),
            self::REWARD_TYPE_CURRENCY => Yii::t('common', 'Монеты'),
        ];
    }

    /**
     * Получить список типов проверок
     * @return array
     */
    public static function getCheckTypeList()
    {
        return [
            self::CHECK_TYPE_VK_SUBSCRIBE_GROUP => Yii::t('common', 'Подписка на группу VK'),
            self::CHECK_TYPE_TELEGRAM_CONNECTED => Yii::t('common', 'Подключение Telegram-бота'),
            self::CHECK_TYPE_TELEGRAM_CHANNEL_SUBSCRIBE => Yii::t('common', 'Подписка на Telegram канал'),
            self::CHECK_TYPE_DISCORD_JOIN => Yii::t('common', 'Вступление в Discord'),
            self::CHECK_TYPE_KILL_BOTS_COUNT => Yii::t('common', 'Убийство ботов (количество)'),
            self::CHECK_TYPE_INVITE_FRIEND => Yii::t('common', 'Приглашение друга'),
            self::CHECK_TYPE_CUSTOM_MANUAL => Yii::t('common', 'Ручная проверка'),
            self::CHECK_TYPE_DAILY_REWARD => Yii::t('common', 'Ежедневная награда'),
            self::CHECK_TYPE_STATISTICS_PARAM => Yii::t('common', 'Параметр статистики'),
            self::CHECK_TYPE_COMMENTS_COUNT => Yii::t('common', 'Количество комментариев'),
            self::CHECK_TYPE_BUILDING_ADD => Yii::t('common', 'Добавление постройки'),
            self::CHECK_TYPE_RADIO_TRACK_ADD => Yii::t('common', 'Добавление музыки в радио'),
            self::CHECK_TYPE_SKIN_ADD => Yii::t('common', 'Добавление скинов (одобренных)'),
            self::CHECK_TYPE_COBALTLAB_REGISTRATION => Yii::t('common', 'Регистрация на CobaltLab'),
            self::CHECK_TYPE_COBALTLAB_FIRST_DEPOSIT => Yii::t('common', 'Первый депозит на CobaltLab'),
        ];
    }

    /**
     * Получить статус ежедневной награды для пользователя
     * @param User $user
     * @param TaskV2UserCompletion|null $completion строка выполнения (из батча); null — загрузить из БД
     * @return array
     */
    protected function getDailyRewardStatus($user, ?TaskV2UserCompletion $completion = null)
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $todayStr = $today->format('Y-m-d');

        if ($completion === null) {
            $completion = TaskV2UserCompletion::find()
                ->where(['task_id' => $this->id, 'user_id' => $user->id])
                ->one();
        }

        if ($completion && $completion->last_completed) {
            $lastCompletedDate = new \DateTime($completion->last_completed);
            $lastCompletedDate->setTime(0, 0, 0);
            $lastCompletedDateStr = $lastCompletedDate->format('Y-m-d');

            // Единственный случай, когда награда недоступна - если получена сегодня
            if ($lastCompletedDateStr === $todayStr) {
                return [
                    'status' => 'completed',
                    'message' => Yii::t('common', 'Награда получена сегодня. Возвращайтесь завтра!'),
                    'count_completed' => $completion->count_completed,
                ];
            }
        }

        // Во всех остальных случаях (вчера, позавчера, никогда) - доступно
        return [
            'status' => 'available',
            'message' => Yii::t('common', 'Доступно'),
            'count_completed' => $completion ? $completion->count_completed : 0,
        ];
    }

    /**
     * Получить список ежедневных наград с текущей позицией пользователя
     * @param User $user
     * @param TaskV2UserCompletion|null $completion предзагруженная строка выполнения (батч API)
     * @return array ['items' => [...], 'currentIndex' => int, 'canReceive' => bool]
     */
    public function getDailyRewardList($user, ?TaskV2UserCompletion $completion = null)
    {
        if ($this->type !== self::TYPE_DAILY_REWARD || $this->check_type !== self::CHECK_TYPE_DAILY_REWARD) {
            return ['items' => [], 'currentIndex' => -1, 'canReceive' => false];
        }

        // Получаем список наград из check_params
        if (empty($this->check_params)) {
            return ['items' => [], 'currentIndex' => -1, 'canReceive' => false];
        }

        // check_params может быть уже массивом или JSON-строкой
        if (is_array($this->check_params)) {
            $params = $this->check_params;
        } else {
            $params = json_decode($this->check_params, true);
        }
        
        if (!is_array($params) || empty($params['rewards']) || !is_array($params['rewards'])) {
            return ['items' => [], 'currentIndex' => -1, 'canReceive' => false];
        }

        $rewards = $params['rewards'];
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $todayStr = $today->format('Y-m-d');

        if ($completion === null) {
            $completion = TaskV2UserCompletion::find()
                ->where(['task_id' => $this->id, 'user_id' => $user->id])
                ->one();
        }

        // Определяем текущий индекс награды
        $currentIndex = 0;
        $canReceive = true;

        if ($completion && $completion->last_completed) {
            $lastCompletedDate = new \DateTime($completion->last_completed);
            $lastCompletedDate->setTime(0, 0, 0);
            $lastCompletedDateStr = $lastCompletedDate->format('Y-m-d');

            // Если последняя награда была сегодня - уже получена
            if ($lastCompletedDateStr === $todayStr) {
                $canReceive = false;
                // Показываем следующую награду (для отображения)
                $currentIndex = ($completion->count_completed) % count($rewards);
            } else {
                // Проверяем, была ли последняя награда вчера
                $yesterday = clone $today;
                $yesterday->modify('-1 day');
                $yesterdayStr = $yesterday->format('Y-m-d');

            if ($lastCompletedDateStr === $yesterdayStr) {
                // Последовательность продолжается - следующая награда
                // count_completed уже включает все предыдущие выполнения
                // Если выполнил 2 раза, то сегодня получит награду с индексом 2 (третий день)
                $currentIndex = ($completion->count_completed) % count($rewards);
            } else {
                // Пропущен день - сброс на первую награду
                $currentIndex = 0;
            }
            }
        } else {
            // Первая награда
            $currentIndex = 0;
        }

        // Если дошли до последней награды - сброс на первую
        if ($currentIndex >= count($rewards)) {
            $currentIndex = 0;
        }

        // Определяем canReceive (можно ли получить награду сегодня)
        $canReceive = true;
        if ($completion && $completion->last_completed) {
            $lastCompletedDate = new \DateTime($completion->last_completed);
            $lastCompletedDate->setTime(0, 0, 0);
            $lastCompletedDateStr = $lastCompletedDate->format('Y-m-d');
            
            if ($lastCompletedDateStr === $todayStr) {
                $canReceive = false;
            }
        }

        // Формируем список наград с информацией
        $items = [];
        foreach ($rewards as $index => $reward) {
            $item = [
                'index' => $index,
                'status' => 'disabled',
                'reward' => $reward, // Сохраняем оригинальные данные награды
            ];

            // Загружаем информацию о предмете/валюте
            // drop_id 843 - это специальный ID для монет (валюты)
            if (isset($reward['drop_id']) && (int)$reward['drop_id'] == 843) {
                // Это валюта (монеты)
                $item['currency'] = 'personal';
                $item['amount'] = isset($reward['amount']) ? (int)$reward['amount'] : 0;
                $item['name'] = Yii::t('common', 'Монеты');
                $item['image'] = '/images/design/icons/64px/coins.png'; // Иконка монет
            } elseif (isset($reward['drop_id'])) {
                // Это предмет
                $drop = Drop::findOneCachedWithImageOrig((int)$reward['drop_id']);
                if ($drop) {
                    $item['drop'] = $drop;
                    $item['image'] = $drop->imageOrig ? $drop->imageOrig->getImagePubUrl() : '';
                    $item['name'] = Yii::t('database', $drop->name ?? '');
                    $item['amount'] = isset($reward['amount']) ? (int)$reward['amount'] : 1;
                }
            } elseif (isset($reward['currency']) && isset($reward['amount'])) {
                $item['currency'] = $reward['currency'];
                $item['amount'] = (int)$reward['amount'];
                $item['name'] = Yii::t('common', 'Монеты');
                $item['image'] = '/images/design/icons/64px/coins.png'; // Иконка монет
            }

            // Определяем статус
            if ($index < $currentIndex) {
                $item['status'] = 'completed';
            } elseif ($index === $currentIndex && $canReceive) {
                $item['status'] = 'available';
            } else {
                $item['status'] = 'disabled';
            }

            $items[] = $item;
        }

        return [
            'items' => $items,
            'currentIndex' => $currentIndex,
            'canReceive' => $canReceive,
            'nextIndex' => !$canReceive ? (($currentIndex + 1) % count($rewards)) : $currentIndex,
        ];
    }

    /**
     * Получить текущую или следующую награду для карточки
     * @param User $user
     * @param TaskV2UserCompletion|null $completion предзагруженная строка выполнения (батч API)
     * @return array|null ['reward' => ..., 'drop' => ..., 'image' => ..., 'name' => ..., 'amount' => ...]
     */
    public function getCurrentDailyReward($user, ?TaskV2UserCompletion $completion = null)
    {
        if ($this->type !== self::TYPE_DAILY_REWARD || $this->check_type !== self::CHECK_TYPE_DAILY_REWARD) {
            return null;
        }

        $rewardList = $this->getDailyRewardList($user, $completion);
        if (empty($rewardList['items'])) {
            return null;
        }

        // Если получил сегодня - показываем следующую награду
        // Если не получил - показываем текущую
        if ($rewardList['canReceive']) {
            // Не получил сегодня - показываем текущую
            $index = $rewardList['currentIndex'];
        } else {
            // Получил сегодня - показываем следующую
            $index = $rewardList['nextIndex'];
        }
        
        if ($index >= count($rewardList['items'])) {
            $index = 0;
        }

        return $rewardList['items'][$index] ?? null;
    }
}
