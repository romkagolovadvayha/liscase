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

    /** Определяет тип нарушения (1/2) по одному сообщению; 3 (спам) требует истории */
    public static function getMuteType(string $message): ?int
    {
        [$msg, $msgCompact] = self::normalizePair($message);

        // --- type 1: родители + брань (оба паттерна небольшие) ---
        if ((preg_match(self::reParents(), $msg) || self::containsParentsCompact($msgCompact))
            && preg_match(self::reObscene(), $msg)) {
            return 1;
        }

        // --- type 2: оскорбление администрации (рядом с бранью, окно ±25 символов) ---
        if (preg_match(self::reAdminInsult(), $msg)) {
            return 2;
        }

        return null;
    }

    /* ================= helpers ================= */

    /** Нормализация: нижний регистр, простые leet-замены, ё→е; возвращаем [обычная, с удалёнными разделителями] */
    private static function normalizePair(string $s): array
    {
        $s = mb_strtolower($s, 'utf-8');

        // базовые look-alike замены для «обыденных» обфускаций
        $map = [
            // латиница -> кириллица
            'a'=>'а','o'=>'о','e'=>'е','p'=>'р','c'=>'с','x'=>'х','y'=>'у','k'=>'к','h'=>'н','m'=>'м','t'=>'т','r'=>'г',
            // цифры/символы, часто подменяют буквы
            '0'=>'о','3'=>'з','4'=>'ч','6'=>'б','@'=>'а','$'=>'с',
            // мягкий знак, «i|l!» как «и»
            'b'=>'ь','i'=>'и','l'=>'и','!'=>'и','1'=>'и',
        ];
        $norm = strtr($s, $map);
        $norm = str_replace('ё', 'е', $norm);

        // схлопнуть >2 одинаковых символа подряд до 2
        $norm = preg_replace('~(.)\1{2,}~u', '$1$1', $norm);
        // нормальные пробелы
        $norm = preg_replace('~\s+~u', ' ', $norm);
        $norm = trim($norm);

        // «компакт» — без пробелов и любых не-букв/не-цифр (ловим «ма-т@b» -> «мать»)
        $compact = preg_replace('~[^\p{L}\p{N}]+~u', '', $norm);

        return [$norm, $compact];
    }

    /** Небольшой список ключевых слов о «родителях» (с морф. хвостами) */
    private static function reParents(): string
    {
        return '~\b(?:'
            . 'мать|мам(?:а|ка|очк\w*|ул\w*)|батя|отец|пап(?:а|очка|аня|уля)?|родител\w*|родак\w*|предк\w*'
            . '|mom(?:my)?|mother|mama|mamka|matka|ojciec|tata|parents?'
            . ')\b~u';
    }

    /** Компакт-проверка: после удаления разделителей слово «родитель» может быть слепленным */
    private static function containsParentsCompact(string $compact): bool
    {
        // Compact-лексемы без пробелов и пунктуации
        $needles = [
            'мать','мама','мамка','батя','отец','папа','родитель','родители','родак','предки',
            'mom','mother','mama','mamka','matka','ojciec','tata','parents',
        ];
        foreach ($needles as $n) {
            if (mb_strpos($compact, $n) !== false) return true;
        }
        return false;
    }

    /** Небольшой «ядро-мат» список (корни/слова), без чрезмерных классов */
    private static function reObscene(): string
    {
        return '~(?:'
            . 'еб|ёб|уеб|уёб|выеб|наеб|проеб|доеб|заеб'
            . '|ху[йиее]|хер|хуесос|пизд|шлюх|сук|долбо[её]б|уебан|уебок|мудак|мудил|гандон|залуп|дроч|трах|сос(?:и|ал|ать)'
            . '|fuck|bitch|motherfuck|pidor|pidr|gandon|blyad|xuy|hui'
            . ')~u';
    }

    /** Слова про администрацию */
    private static function reAdminWord(): string
    {
        return '~(?:админ\w*|модер\w*|модератор\w*|admin\w*|moder\w*)~u';
    }

    /** Админ рядом с бранью (окно ±25 символов) */
    private static function reAdminInsult(): string
    {
        $adm = trim(self::reAdminWord(), '~u');
        $obs = trim(self::reObscene(),  '~u');
        return '~(?:' . $adm . '.{0,25}' . $obs . '|' . $obs . '.{0,25}' . $adm . ')~u';
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
        if (empty($reasons[$type])) {
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

    public static function actionSuccessTrack($buttonValueObj) {
        $trackId = ArrayHelper::getValue($buttonValueObj, 'track_id');
        
        $track = \common\models\radio\RadioTrack::findOne($trackId);
        
        if (!$track) {
            return '⛔ Трек не найден!';
        }
        
        if ($track->status === \common\models\radio\RadioTrack::STATUS_ACTIVE) {
            return '⛔ Трек уже подтвержден!';
        }
        
        $track->status = \common\models\radio\RadioTrack::STATUS_ACTIVE;
        if ($track->save()) {
            // Добавить в очередь Node.js через прямой вызов
            if ($track->radioStation && $track->filename) {
                $station = $track->radioStation;
                
                try {
                    $nodeApiUrl = "http://localhost:{$station->port}/api/reload";
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 3,
                            'ignore_errors' => true,
                        ]
                    ]);
                    
                    @file_get_contents($nodeApiUrl, false, $context);
                } catch (\Exception $e) {
                    Yii::error("Node.js API error: " . $e->getMessage(), __METHOD__);
                }
            }
            
            if (!empty($track->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage(
                    $track->user->telegram_chat_id, 
                    '🎵 Ваш трек "' . $track->title . '" успешно прошёл модерацию и добавлен на радиостанцию!'
                );
            }
            
            return [
                'editMessageReplyMarkup' => true,
                'buttons' => [
                    [
                        'text' => '🔴 Отклонить',
                        'callback_data' => json_encode([
                            'action' => 'reject-track',
                            'track_id' => $trackId,
                        ])
                    ]
                ],
            ];
        }
        return '⛔ Произошла ошибка';
    }

    public static function actionRejectTrack($buttonValueObj) {
        $trackId = ArrayHelper::getValue($buttonValueObj, 'track_id');
        
        $track = \common\models\radio\RadioTrack::findOne($trackId);
        
        if (!$track) {
            return '⛔ Трек не найден!';
        }
        
        if ($track->status === \common\models\radio\RadioTrack::STATUS_REJECT) {
            return '⛔ Трек уже отклонен!';
        }
        
        $track->status = \common\models\radio\RadioTrack::STATUS_REJECT;
        if ($track->save()) {
            if (!empty($track->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage(
                    $track->user->telegram_chat_id, 
                    '🎵 Ваш трек "' . $track->title . '" не прошёл модерацию.'
                );
            }
            
            return [
                'editMessageReplyMarkup' => true,
                'buttons' => [
                    [
                        'text' => '🟢 Принять',
                        'callback_data' => json_encode([
                            'action' => 'success-track',
                            'track_id' => $trackId,
                        ])
                    ],
                ],
            ];
        }
        return '⛔ Произошла ошибка';
    }
}
