<?php

namespace backend\models;

use common\components\queue\telegram\SendMessageJob;
use common\components\queue\telegram\SendPhotoJob;
use common\components\queue\telegram\TelegramJob;
use common\components\queue\telegram\TelegramMassJob;
use common\components\telegram\TelegramPersonalBot;
use common\models\country\Country;
use common\models\country\CountryPromo;
use common\models\credit\Credit;
use common\models\invoice\Invoice;
use common\models\package\Marathon;
use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\user\UserBlogger;
use common\models\user\UserSocialNetwork;
use common\models\userInvestor\UserInvestor;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * This is the model class for table "telegram_constructor".
 *
 * @property int $id
 * @property string $title
 * @property int $audience_id
 * @property int $bot_id
 * @property int $telegram_constructor_message_id
 * @property int $status
 * @property string|null $created_at
 *
 * @property TelegramConstructorMessage $telegramConstructorMessage
 */
class TelegramConstructor extends \yii\db\ActiveRecord
{

    public const STATUS_NEW = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_SUCCESS = 3;
    public const STATUS_ERROR = 4;

    public const PERSONAL_BOT = 1;
    public const OTHER_BOT = 2;

    public const AUDIENCE_TEST = 1;
    public const AUDIENCE_ALL = 2;
    public const AUDIENCE_WINNER = 3;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['bot_id', 'telegram_constructor_message_id', 'title', 'audience_id'], 'required'],
            [['bot_id', 'status', 'telegram_constructor_message_id', 'audience_id'], 'integer'],
            [['created_at', 'status'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Название рассылки',
            'audience_id' => 'Аудитория',
            'bot_id' => 'Бот',
            'status' => 'Статус',
            'telegram_constructor_message_id' => 'Сообщение',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * @throws \Exception
     */
    public function saveRecord(): bool
    {
        try {
            $this->status = self::STATUS_NEW;
            $this->created_at = date('Y-m-d H:i:s');
            $this->save(false);
        } catch (\Exception $e) {
            \Yii::info("Telegram message not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

    /**
     * @return string[]
     */
    public static function getBotList(): array
    {
        return [
            self::PERSONAL_BOT => 'Персональный бот',
//            self::OTHER_BOT => 'Other Bot'
        ];
    }

    /**
     * @return string[]
     */
    public static function getAudienceList(): array
    {
        return [
            self::AUDIENCE_TEST => 'Тестовая аудитория',
            self::AUDIENCE_ALL => 'Все пользователи',
            self::AUDIENCE_WINNER => 'Победители',
        ];
    }

    /**
     * @return string[]
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_NEW => 'Создан',
            self::STATUS_IN_PROGRESS => 'В процессе',
            self::STATUS_SUCCESS => 'Завершен',
            self::STATUS_ERROR => 'Ошибка'
        ];
    }

    /**
     * @return bool|void
     */
    public function sendPersonalBot()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        foreach (self::getAudience($this->audience_id) as $userId) {
            /** @var User $user */
            $user = User::findOne($userId);
            $cacheKey = "sendPersonalBot_{$this->telegramConstructorMessage->id}_{$user->current_language}";
            $cacheData = Yii::$app->cache->get($cacheKey);
            if (!empty($cacheData)) {
                $message = $cacheData['message'];
                $photo = $cacheData['photo'];
                $buttons = $cacheData['buttons'];
            } else {
                $buttons = $this->telegramConstructorMessage->getTelegramButtons($user->current_language);
                $message = $this->telegramConstructorMessage->getTelegramMessage($user->current_language, !empty($buttons));
                $photo = null;
                if (!empty($this->telegramConstructorMessage->getImageLink($user->current_language))) {
                    $photo = $this->telegramConstructorMessage->getPubUrl('', $user->current_language);
                }
                Yii::$app->cache->set($cacheKey, [
                    'message' => $message,
                    'photo' => $photo,
                    'buttons' => $buttons
                ], 60);
            }
            if (!empty($buttons) || empty($photo)) {
                Yii::$app->queueTelegram->push(new SendMessageJob([
                                                                      'telegram_chat_id' => $user->telegram_chat_id,
                                                                      'message' => $message,
                                                                      'buttons' => $buttons,
                ]));
            } else {
                Yii::$app->queueTelegram->push(new SendPhotoJob([
                                                                      'telegram_chat_id' => $user->telegram_chat_id,
                                                                      'photo' => $photo,
                                                                      'message' => $message,
                                                                  ]));
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public static function getAudience($audienceId) {
        if ($audienceId == self::AUDIENCE_TEST) {
            return User::find()
                                    ->select('DISTINCT(u.id)')
                                    ->alias('u')
                                    ->andWhere(['u.status' => User::STATUS_ACTIVE])
                                    ->andWhere('telegram_chat_id is NOT NULL')
                                    ->andWhere(['IN', 'u.id', [509]])
//                                    ->andWhere(['IN', 'u.id', [2373, 509]])
                                    ->createCommand()
                                    ->queryColumn();
        }
        if ($audienceId == self::AUDIENCE_ALL) {
            return User::find()
                       ->select('DISTINCT(u.id)')
                       ->alias('u')
                       ->andWhere(['u.status' => User::STATUS_ACTIVE])
                       ->andWhere('telegram_chat_id is NOT NULL')
                       ->createCommand()
                       ->queryColumn();
        }
        if ($audienceId == self::AUDIENCE_WINNER) {
            return User::find()
                       ->select('DISTINCT(id)')
                       ->andWhere(['IN', 'steam_id', [76561198166483284, 76561198162114176, 76561199729196379, 76561198071379438, 76561199070076939]])
                       ->andWhere('telegram_chat_id is NOT NULL')
                       ->createCommand()
                       ->queryColumn();
        }
        return [];
    }

    /**
     * Gets query for [[TelegramConstructorMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTelegramConstructorMessage()
    {
        return $this->hasOne(TelegramConstructorMessage::class, ['id' => 'telegram_constructor_message_id']);
    }
}
