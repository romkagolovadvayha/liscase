<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\building\Building;
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

    /** Определяет тип нарушения в одном сообщении */
    public static function getMuteType(string $message): ?int
    {
        $msg = self::normalize($message);

        // --- type 1: оскорбление родителей ---
        if (preg_match(self::reParents(), $msg) && preg_match(self::reObscene(), $msg)) {
            return 1;
        }

        // --- type 2: оскорбление администрации ---
        if (preg_match(self::reAdminInsult(), $msg)) {
            return 2;
        }

        // type 3 (спам) определяется только по повторениям подряд,
        // тут мы не можем выявить без истории, поэтому пропускаем.
        return null;
    }

    /* ========== вспомогательные приватные методы ========== */

    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'utf-8');
        $map = [
            'a'=>'а','b'=>'ь','c'=>'с','e'=>'е','h'=>'н','k'=>'к','m'=>'м','o'=>'о',
            'p'=>'р','r'=>'г','t'=>'т','x'=>'х','y'=>'у','6'=>'б','0'=>'о','3'=>'з',
            '4'=>'ч','@'=>'а','$'=>'с','€'=>'е'
        ];
        $text = strtr($text, $map);
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('~[^а-яa-z0-9\s]~u', ' ', $text);
        $text = preg_replace('~(.)\1{2,}~u', '$1$1', $text);
        return trim(preg_replace('~\s+~u', ' ', $text));
    }

    private static function reParents(): string
    {
        return '~(?:мат[ьи]|мам(?:а|ка|ке|ку|ке|ки)?|бат[ья]|отец|пап(?:а|ка|ке|ку)?|родител(?:ь|и|ей|ям|ями)|родак(?:и|ов|ам)?)~u';
    }

    private static function reObscene(): string
    {
        return '~(?:еб|ху[йеёи]|пизд|шлюх|сук|долбоё?б|уеб|мудак|дроч|трах|сос|fuck|bitch|motherfuck)~u';
    }

    private static function reAdminInsult(): string
    {
        $adm = '(?:админ(?:ы|ам|ов|е)?|модер(?:атор|ы|а|у)?|модератор(?:ы|ам|ов)?)';
        $obs = self::reObscene();
        return '~(?:' . $adm . '.{0,20}' . trim($obs,'~u') . '|' . trim($obs,'~u') . '.{0,20}' . $adm . ')~u';
    }

    public static function mute($type, $message, $steamId)
    {
        if (empty($type)) {
            return false;
        }
        if (empty($steamId)) {
            return false;
        }
        if (empty($message)) {
            return false;
        }

        $info = Chats::muteReason()[$type];

        $reasons = self::muteReason();
        if (empty($reasons[$data['type']])) {
            return false;
        }

        $user = User::findBySteamId($steamId, false, "Chats");
        if (empty($user)) {
            return false;
        }

        $message = "💭 <b>Подозрение на оскорбление</b>" . PHP_EOL
            . "Отправил: {$user->username} ({$steamId})" . PHP_EOL
            . "Сообщение: {$message}" . PHP_EOL
            . "Причина: {$info['reason']}" . PHP_EOL
            . "Сервер: {$user->getCurrentServer()->name}";

        Yii::$app->telegramSupport->sendMessage($message, [
            [
                'text' => '🔴 Замутить игрока',
                'callback_data' => json_encode([
                    'action'   => 'mute',
                    'steam_id' => $steamId,
                    'type' => $type,
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

        RconTasks::execute("ra.mute {$steamId} \"{$reasonData['reason']}\" {$hour}h --broadcast", [$user->getCurrentServer()->tag]);

        return '✅ Игрок успешно замучен!';
    }

    public static function actionSuccessSkin($buttonValueObj) {
        $skinId = ArrayHelper::getValue($buttonValueObj, 'skin_id');

        /** @var ServerSkin $model */
        $model = ServerSkin::find()
                ->andWhere(['skin_id' => $skinId])
                ->one();

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

    public static function actionSuccessBuilding($buttonValueObj) {
        $id = ArrayHelper::getValue($buttonValueObj, 'id');
        /** @var Building $model */
        $model = Building::findOne($id);

        if ($model->status === Building::STATUS_ACTIVE) {
            return '⛔ Постройка уже подтверждена!';
        }
        $model->status = Building::STATUS_ACTIVE;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '🏠 Ваша постройка успешно прошла модерацию!');
            }
            return [
                'editMessageReplyMarkup' => true,
                'buttons' =>        [
                    [
                        'text' => '🔴 Отклонить',
                        'callback_data' => json_encode([
                                                           'action'   => 'reject-building',
                                                           'id'  => $id,
                                                       ])
                    ]
                ],
            ];
        }
        return '⛔ Произошла ошибка';
    }

    public static function actionRejectBuilding($buttonValueObj) {
        $id = ArrayHelper::getValue($buttonValueObj, 'id');
        /** @var Building $model */
        $model = Building::findOne($id);
        if ($model->status === Building::STATUS_REJECT) {
            return '⛔ Скин уже отклонен!';
        }
        $model->status = Building::STATUS_REJECT;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '🏠 Ваша постройка не прошла модерацию!');
            }
            return [
                'editMessageReplyMarkup' => true,
                'buttons' =>        [
                    [
                        'text' => '🟢 Принять',
                        'callback_data' => json_encode([
                                                           'action'   => 'success-building',
                                                           'id'  => $id,
                                                       ])
                    ],
                ],
            ];
        }
        return '⛔ Произошла ошибка';
    }
}
