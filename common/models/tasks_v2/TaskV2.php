<?php

namespace common\models\tasks_v2;

use common\components\base\ActiveRecord;
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

    const REWARD_TYPE_ITEM = 'item';
    const REWARD_TYPE_CURRENCY = 'currency';

    const CHECK_TYPE_VK_SUBSCRIBE_GROUP = 'vk_subscribe_group';
    const CHECK_TYPE_TELEGRAM_CONNECTED = 'telegram_connected';
    const CHECK_TYPE_DISCORD_JOIN = 'discord_join';
    const CHECK_TYPE_KILL_BOTS_COUNT = 'kill_bots_count';
    const CHECK_TYPE_INVITE_FRIEND = 'invite_friend';
    const CHECK_TYPE_CUSTOM_MANUAL = 'custom_manual';
    const CHECK_TYPE_DAILY_REWARD = 'daily_reward';
    const CHECK_TYPE_STATISTICS_PARAM = 'statistics_param';
    const CHECK_TYPE_COMMENTS_COUNT = 'comments_count';
    const CHECK_TYPE_BUILDING_ADD = 'building_add';
    const CHECK_TYPE_RADIO_TRACK_ADD = 'radio_track_add';

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
            [['type'], 'in', 'range' => [self::TYPE_ONE_TIME, self::TYPE_REPEATABLE, self::TYPE_DAILY_REWARD]],
            [['reward_type'], 'in', 'range' => [self::REWARD_TYPE_ITEM, self::REWARD_TYPE_CURRENCY]],
            [['reward_item_id', 'per_user_limit', 'global_limit', 'global_completed', 'max_progress', 'is_active', 'is_visible_for_guests', 'sort'], 'integer'],
            [['reward_amount'], 'number'],
            [['check_params', 'extra_buttons'], 'safe'],
            [['created_at', 'updated_at'], 'safe'],
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
            'sort' => Yii::t('common', 'Сортировка'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
            'imageFile' => Yii::t('common', 'Изображение'),
        ];
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

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserCompletions()
    {
        return $this->hasMany(TaskV2UserCompletion::class, ['task_id' => 'id']);
    }

    /**
     * Получить статус задания для пользователя
     * @param User|null $user
     * @return array Статус: 'available', 'completed', 'limit_reached', 'unavailable'
     */
    public function getUserStatus($user)
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

        // Отключаем кэш для тестирования
        $completion = TaskV2UserCompletion::find()
            ->where(['task_id' => $this->id, 'user_id' => $user->id])
            ->one();

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
            return $this->getDailyRewardStatus($user);
        }

        // Для одноразовых заданий
        if ($this->type === self::TYPE_ONE_TIME && $countCompleted > 0) {
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
            self::CHECK_TYPE_DISCORD_JOIN => Yii::t('common', 'Вступление в Discord'),
            self::CHECK_TYPE_KILL_BOTS_COUNT => Yii::t('common', 'Убийство ботов (количество)'),
            self::CHECK_TYPE_INVITE_FRIEND => Yii::t('common', 'Приглашение друга'),
            self::CHECK_TYPE_CUSTOM_MANUAL => Yii::t('common', 'Ручная проверка'),
            self::CHECK_TYPE_DAILY_REWARD => Yii::t('common', 'Ежедневная награда'),
            self::CHECK_TYPE_STATISTICS_PARAM => Yii::t('common', 'Параметр статистики'),
            self::CHECK_TYPE_COMMENTS_COUNT => Yii::t('common', 'Количество комментариев'),
            self::CHECK_TYPE_BUILDING_ADD => Yii::t('common', 'Добавление постройки'),
            self::CHECK_TYPE_RADIO_TRACK_ADD => Yii::t('common', 'Добавление музыки в радио'),
        ];
    }

    /**
     * Получить статус ежедневной награды для пользователя
     * @param User $user
     * @return array
     */
    protected function getDailyRewardStatus($user)
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $todayStr = $today->format('Y-m-d');

        // Проверяем через TaskV2UserCompletion - это более надежно, чем через Profit
        // Проверяем, было ли задание выполнено СЕГОДНЯ
        $completion = TaskV2UserCompletion::find()
            ->where(['task_id' => $this->id, 'user_id' => $user->id])
            ->one();

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
     * @return array ['items' => [...], 'currentIndex' => int, 'canReceive' => bool]
     */
    public function getDailyRewardList($user)
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

        // Получаем запись о выполнении из TaskV2UserCompletion
        $completion = TaskV2UserCompletion::find()
            ->where(['task_id' => $this->id, 'user_id' => $user->id])
            ->one();

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
                $drop = \common\models\box\Drop::findOne($reward['drop_id']);
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
     * @return array|null ['reward' => ..., 'drop' => ..., 'image' => ..., 'name' => ..., 'amount' => ...]
     */
    public function getCurrentDailyReward($user)
    {
        if ($this->type !== self::TYPE_DAILY_REWARD || $this->check_type !== self::CHECK_TYPE_DAILY_REWARD) {
            return null;
        }

        $rewardList = $this->getDailyRewardList($user);
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

