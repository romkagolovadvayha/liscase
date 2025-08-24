<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\rcon\RconTasks;
use common\models\serverskin\ServerSkin;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $message
 * @property bool   $is_muted
 * @property string $created_at
 * @property string $server_tag
 */
class Chats extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'servers_chats';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'          => Yii::t('common', 'ID'),
            'steam_id'    => Yii::t('common', 'Steam ID'),
            'message'    => Yii::t('common', 'Сообщение'),
            'created_at'    => Yii::t('common', 'Дата'),
            'server_tag'    => Yii::t('common', 'Сервер'),
        ];
    }

    public static function muteReason()
    {
        return [
          1 => [
              'reason' => 'Оскорбление родителей',
              'term' => 5 * 60 * 60,
          ],
          2 => [
              'reason' => 'Оскорбление администрации',
              'term' => 5 * 60 * 60,
          ],
          3 => [
              'reason' => 'Оскорбление администрации',
              'term' => 2 * 60 * 60,
          ],
        ];
    }

    public static function mute($data)
    {
        if (empty($data['steam_id'])) {
            return false;
        }
        if (empty($data['type'])) {
            return false;
        }
        if (empty($data['message'])) {
            return false;
        }

        $reasons = self::muteReason();
        if (empty($reasons[$data['type']])) {
            return false;
        }
        $reasonData = $reasons[$data['type']];
        $hour = $reasonData['term']/60/60;

        $user = User::findBySteamId($data['steam_id'], false, "Chats");
        if (empty($user)) {
            return false;
        }

        $message = "💭 <b>Подозрение на оскорбление</b>" . PHP_EOL
            . "Отправил: {$user->username} ({$user->steam_id})" . PHP_EOL
            . "Сообщение: {$data['message']}" . PHP_EOL
            . "Причина: {$reasonData['reason']}" . PHP_EOL
            . "Сервер: {$user->getCurrentServer()->name}";

        Yii::$app->telegramSupport->sendMessage($message, [
            [
                'text' => '🔴 Замутить игрока',
                'callback_data' => json_encode([
                    'action'   => 'mute',
                    'steam_id' => $data['steam_id'],
                    'type' => $data['type'],
                ])
            ]
        ]);

        return true;
    }

    public static function actionMute($buttonValueObj) {
        $type = ArrayHelper::getValue($buttonValueObj, 'type');
        $steamId = ArrayHelper::getValue($buttonValueObj, 'steam_id');

        $reasons = self::muteReason();
        if (empty($reasons[$type])) {
            return '⛔ Произошла ошибка';
        }
        $reasonData = $reasons[$type];

        $user = User::findBySteamId($steamId, false, "Chats");
        if (empty($user)) {
            return '⛔ Произошла ошибка';
        }
        $hour = $reasonData['term']/60/60;

        RconTasks::execute("ra.mute {$steamId} \"{$reasonData['reason']}\" {$hour}h", [$user->getCurrentServer()->tag]);

        return '✅ Игрок успешно замучен!';
    }

    public static function actionSuccessSkin($buttonValueObj) {
        $skinId = ArrayHelper::getValue($buttonValueObj, 'skin_id');

        /** @var ServerSkin $model */
        $model = ServerSkin::find()
                ->andWhere(['skin_id' => $skinId])
                ->one();
        Yii::$app->telegramChats->sendMessage(json_encode($buttonValueObj));
        if ($model->status === ServerSkin::STATUS_ACTIVE) {
            return '⛔ Скин уже подтвержден!';
        }
        $model->status = ServerSkin::STATUS_ACTIVE;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '👕 Ваш скин успешно прошел модерацию и добавлен на сервера!');
            }
            RconTasks::execute("skinbox.addskin {$model->skin_id}");
            return [
                'editMessageReplyMarkup' => true,
                'buttons' =>        [
                    [
                        'text' => '🔴 Отклонить',
                        'callback_data' => json_encode([
                                                           'action'   => 'reject-skin',
                                                           'skin_id'  => $skinId,
                                                       ])
                    ]
                ],
            ];
        }
        return '⛔ Произошла ошибка';
    }

    public static function actionRejectSkin($buttonValueObj) {
        $skinId = ArrayHelper::getValue($buttonValueObj, 'skin_id');

        /** @var ServerSkin $model */
        $model = ServerSkin::find()
                           ->andWhere(['skin_id' => $skinId])
                           ->one();
        if ($model->status === ServerSkin::STATUS_REJECT) {
            return '⛔ Скин уже отклонен!';
        }
        $model->status = ServerSkin::STATUS_REJECT;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '👕 Ваш скин не прошел модерацию!');
            }
            RconTasks::execute("skinbox.removeskin {$model->skin_id}");
            return [
                'editMessageReplyMarkup' => true,
                'buttons' =>        [
                    [
                        'text' => '🟢 Принять',
                        'callback_data' => json_encode([
                                                           'action'   => 'success-skin',
                                                           'skin_id'  => $skinId,
                                                       ])
                    ],
                ],
            ];
        }
        return '⛔ Произошла ошибка';
    }
}
