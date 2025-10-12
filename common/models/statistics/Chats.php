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

    /** Вернёт 1, 2 или null (type=3 нужен контекст истории сообщений) */
    public static function getMuteType(string $message): ?int
    {
        $msg = self::normalize($message);

        // 1) родители + obscene
        if (preg_match(self::reParents(), $msg) && preg_match(self::reObscene(), $msg)) {
            return 1;
        }

        // 2) админ рядом с obscene (±25 символов)
        if (preg_match(self::reAdminInsult(), $msg)) {
            return 2;
        }

        return null;
    }

    /* ================= helpers ================= */

    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'utf-8');

        // убрать zero-width, комбинирующие знаки
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Mn}]/u', '', $s);

        // унификация ё→е
        $s = str_replace('ё', 'е', $s);

        // лёгкая «латиница -> кириллица/близкие» (оставляем часть на регексп-уровне)
        $quick = [
            '0'=>'о','3'=>'з','4'=>'ч','6'=>'б','@'=>'а','$'=>'с','€'=>'е','!'=>'и','1'=>'l',
        ];
        $s = strtr($s, $quick);

        // схлопнуть повторы символов (больше 3 → 2)
        $s = preg_replace('~(.)\1{2,}~u', '$1$1', $s);

        // пробелы нормализуем
        $s = preg_replace('~\s+~u', ' ', $s);

        return trim($s);
    }

    /** Разрешённый «шум» между буквами (до 3 любых разделителей) */
    private static function sep(): string
    {
        return '(?:[\s\p{P}\p{S}_\-]{0,3})';
    }

    /** Мэп похожих символов для одной буквы → класс символов */
    private static function glyph(string $ch): string
    {
        // NB: порядок важен, шире классы для популярных подмен
        static $map = [
            'а' => '[аa@4]',
            'б' => '[б6b]',
            'в' => '[вvb8]',
            'г' => '[гrɡ]',
            'д' => '[дg]',
            'е' => '[еe€3]',
            'ж' => '[ж*x]',
            'з' => '[з3]',
            'и' => '[иiu!1l]',
            'й' => '[ийiu!1l]',
            'к' => '[кk]',
            'л' => '[лl1|]',
            'м' => '[ммm]',
            'н' => '[нh]',
            'о' => '[оo0]',
            'п' => '[пn]',
            'р' => '[рp]',
            'с' => '[сsc$]',
            'т' => '[тt+]',
            'у' => '[уy]',
            'ф' => '[фfph]',
            'х' => '[хx%]',
            'ц' => '[цu]',
            'ч' => '[ч4]',
            'ш' => '[шwщ]',
            'щ' => '[щwш]',
            'ьы' => '(?:[ьы]+)', // мягкий блок
            'ь' => '[ьb\']',
            'ы' => '[ыbi]',
            'я' => '[я9r]',
            // латиница для иностранных слов
            'a' => '[aа@4]',
            'b' => '[bв8]',
            'c' => '[cс$]',
            'd' => '[dcl]',
            'e' => '[eе€3]',
            'f' => '[fфph]',
            'g' => '[gд9]',
            'h' => '[hн#]',
            'i' => '[iи!1l]',
            'j' => '[jј]',
            'k' => '[kк]',
            'l' => '[l1|]',
            'm' => '[mм]',
            'n' => '[nп]',
            'o' => '[oо0]',
            'p' => '[pр]',
            'q' => '[qкo]',
            'r' => '[rг]',
            's' => '[sс$5]',
            't' => '[tт+7]',
            'u' => '[uиц]',
            'v' => '[v∨]',
            'w' => '[wшщ]',
            'x' => '[xх%]',
            'y' => '[yу]',
            'z' => '[zѕ2]',
        ];

        // если есть составные/двухсимвольные случаи
        if ($ch === 'й') return $map['й'];
        if ($ch === 'ы') return $map['ы'];
        if ($ch === 'ь') return $map['ь'];

        return $map[$ch] ?? preg_quote($ch, '~');
    }

    /** Построить паттерн для слова с шумами и похожими символами */
    private static function buildWordRegex(string $word, bool $allowTail = true): string
    {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        $pieces = [];
        foreach ($chars as $i => $ch) {
            $pieces[] = self::glyph($ch);
            if ($i < count($chars) - 1) {
                $pieces[] = self::sep();
            }
        }
        $tail = $allowTail ? '(?:[а-яa-z]{0,3})?' : '';
        return '(?:' . implode('', $pieces) . $tail . ')';
    }

    /** Большой список «родителей» (ru/ua/pl/en + сленг) */
    private static function reParents(): string
    {
        $words = [
            // RU
            'мать','маман','мамаша','мамка','мамочка','мамуля','мамуля','маманя','маменька','мама',
            'батя','батяня','батяра','папа','папаша','папаня','папочка','папуля',
            'отец','батяня','родитель','родители','родак','родаков','предки',
            // UA
            'мати','мамця','татко','тато','отець','батько','батьки',
            // PL
            'matka','mama','mamusia','tata','tatus','ojciec','rodzic','rodzice',
            // EN (для смешанных фраз)
            'mom','mommy','mother','dad','daddy','father','parents','folks',
        ];
        $alts = array_map(fn($w) => self::buildWordRegex($w, true), $words);
        return '~(?:' . implode('|', $alts) . ')~u';
    }

    /** Расширённые корни брани/сексуальной лексики (ru/en + сленг) */
    private static function reObscene(): string
    {
        $roots = [
            // RU корни
            'еб','ёб','выеб','наеб','подъеб','проеб','доеб','заеб','уеб','уёб',
            'хуй','хер','хуесос','хуепл','хуеб','охуел','похер',
            'пизд','бляд','бля','шлюх','сука','сук','мраз','говн','дерьм','сатан',
            'уёб','уеб','долбоёб','долбоеб','уебан','уебок','ебанат','ебан',
            'мудaк','мудак','мудил','гандон','презик','залуп','член','пенис','минет','оральн','анал','задниц','секс',
            'соси','сосал','сосать','отсос','насосал','отсасыв',
            'трах','траха','траx','перд','срать','насрать',
            // EN
            'fuck','fucker','fucking','motherfuck','bitch','slut','asshole','dick','cock','suck','sucked','sucking',
            // транслит и часто встречающиеся
            'pidor','pidr','pedik','pedor','gandon','blyad','suka','xuy','hui','huy','ebal','eban',
        ];

        // строим паттерны с шумами внутри каждого корня
        $alts = array_map(fn($w) => self::buildWordRegex($w, true), $roots);
        return '~(?:' . implode('|', $alts) . ')~u';
    }

    /** Слова про администрацию/модерацию (ru/en + сленг) */
    private static function reAdminWord(): string
    {
        $words = [
            'админ','админы','админа','админка','администрац','админский','админов',
            'модер','модерка','модеры','модератор','модерация','куратор','хелпер','хелперы','стажер','стажёр','персонал',
            // EN
            'admin','admins','adm1n','mod','moder','moderator','staff','helper','helpers','support',
        ];
        $alts = array_map(fn($w) => self::buildWordRegex($w, true), $words);
        return '~(?:' . implode('|', $alts) . ')~u';
    }

    /** Админ + брань в окне ±25 символов */
    private static function reAdminInsult(): string
    {
        $adm = trim(self::reAdminWord(), '~u');
        $obs = trim(self::reObscene(), '~u');
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
