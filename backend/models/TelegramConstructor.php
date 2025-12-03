<?php

namespace backend\models;

use common\components\queue\telegram\SendMessageJob;
use common\components\queue\telegram\SendPhotoJob;
use common\components\queue\telegram\TelegramJob;
use common\components\queue\telegram\TelegramMassJob;
use common\components\queue\vk\SendVkMessageJob;
use common\components\telegram\TelegramPersonalBot;
use common\components\vk\VkApiHelper;
use common\components\helpers\Role;
use common\models\vk\VkUser;
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
 * @property bool $only_with_user Отправлять только пользователям с привязанным user (для VK)
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
    public const VK_GROUP = 2;
    public const OTHER_BOT = 3;

    public const AUDIENCE_TEST = 1;
    public const AUDIENCE_ALL = 2;
    public const AUDIENCE_WINNER = 3;
    public const AUDIENCE_MODERATORS = 4;

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
            [['only_with_user'], 'boolean'],
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
            'bot_id' => 'Платформа',
            'status' => 'Статус',
            'telegram_constructor_message_id' => 'Сообщение',
            'only_with_user' => 'Только для пользователей с привязанным user',
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
            
            // Приводим к int для корректного сохранения
            $this->bot_id = (int)$this->bot_id;
            $this->audience_id = (int)$this->audience_id;
            $this->telegram_constructor_message_id = (int)$this->telegram_constructor_message_id;
            
            if (!$this->save(false)) {
                \Yii::error("TelegramConstructor save failed: " . json_encode($this->errors, JSON_UNESCAPED_UNICODE), __METHOD__);
                return false;
            }
        } catch (\Exception $e) {
            \Yii::error("TelegramConstructor save exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $this->addError('id', 'Ошибка сохранения: ' . $e->getMessage());
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
            self::PERSONAL_BOT => 'Telegram: Персональный бот',
            self::VK_GROUP => 'ВКонтакте: Группа',
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
            self::AUDIENCE_MODERATORS => 'Модераторы и админы',
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
     * Универсальный метод отправки рассылки
     * @return bool
     */
    public function send()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        switch ($this->bot_id) {
            case self::PERSONAL_BOT:
                return $this->sendPersonalBot();
            case self::VK_GROUP:
                return $this->sendVkGroup();
            default:
                Yii::error("Unknown bot_id: {$this->bot_id}", __METHOD__);
                return false;
        }
    }

    /**
     * Отправка в Telegram персональный бот
     * @return bool|void
     */
    public function sendPersonalBot()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        foreach (self::getAudience($this->audience_id, self::PERSONAL_BOT) as $userId) {
            /** @var User $user */
            $user = User::findOne($userId);
            if (empty($user) || empty($user->telegram_chat_id)) {
                continue;
            }
            
            // Для каждого пользователя нужна своя ссылка (с подстановкой user_id)
            // Поэтому не кэшируем photo, если это ссылка с плейсхолдером
            $imageLink = $this->telegramConstructorMessage->getImageLink($user->current_language);
            $isDynamicLink = !empty($imageLink) && strpos($imageLink, '@') === 0 && strpos($imageLink, '{user_id}') !== false;
            
            $cacheKey = "sendPersonalBot_{$this->telegramConstructorMessage->id}_{$user->current_language}";
            $cacheData = Yii::$app->cache->get($cacheKey);
            
            if (!empty($cacheData) && !$isDynamicLink) {
                $message = $cacheData['message'];
                $photo = $cacheData['photo'];
                $buttons = $cacheData['buttons'];
            } else {
                $buttons = $this->telegramConstructorMessage->getTelegramButtons($user->current_language);
                $message = $this->telegramConstructorMessage->getTelegramMessage($user->current_language, !empty($buttons));
                $photo = null;
                if (!empty($imageLink)) {
                    // Передаем user_id для подстановки в ссылку
                    $photo = $this->telegramConstructorMessage->getPubUrl('', $user->current_language, $user->id);
                }
                
                // Кэшируем только если ссылка не динамическая
                if (!$isDynamicLink) {
                    Yii::$app->cache->set($cacheKey, [
                        'message' => $message,
                        'photo' => $photo,
                        'buttons' => $buttons
                    ], 60);
                }
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
     * Отправка в личные сообщения участников группы ВКонтакте
     * @return bool
     */
    public function sendVkGroup()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $groupId = Yii::$app->settings->get('vk_group_id');
        if (empty($groupId)) {
            Yii::error("VK group_id is not configured", __METHOD__);
            return false;
        }

        // Используем русский язык по умолчанию
        $language = 'ru-RU';
        $message = $this->telegramConstructorMessage->getVkMessage($language);
        $imageLink = $this->telegramConstructorMessage->getImageLink($language);
        
        // Проверяем, является ли ссылка динамической (с плейсхолдером {user_id})
        $isDynamicLink = !empty($imageLink) && strpos($imageLink, '@') === 0 && strpos($imageLink, '{user_id}') !== false;

        // Получаем список участников группы с учетом фильтрации по аудитории
        $recipients = self::getAudience($this->audience_id, self::VK_GROUP, !empty($this->only_with_user));
        if (empty($recipients)) {
            Yii::error("VK: No recipients found for audience {$this->audience_id}, only_with_user: " . ($this->only_with_user ? 'true' : 'false'), __METHOD__);
            return false;
        }

        // Отправляем сообщения через очередь VK
        foreach ($recipients as $vkUserId) {
            $photo = null;
            if (!empty($imageLink)) {
                // Для динамических ссылок нужно подставить user_id из базы данных, а не vk_user_id
                // Ищем пользователя по vk_id
                $user = User::find()
                    ->where(['vk_id' => $vkUserId])
                    ->one();
                
                // Если пользователь найден, используем его user_id, иначе используем vk_user_id
                $userIdForUrl = $user ? $user->id : $vkUserId;
                
                if (!$user) {
                    Yii::warning("VK: User not found for vk_id {$vkUserId}, using vk_user_id for URL", __METHOD__);
                }
                
                $photo = $this->telegramConstructorMessage->getPubUrl('', $language, $userIdForUrl);
                
                if (empty($photo)) {
                    Yii::warning("VK: Empty photo URL for vk_user_id {$vkUserId}, user_id {$userIdForUrl}", __METHOD__);
                }
            }
            
            Yii::$app->queueVk->push(new SendVkMessageJob([
                'user_id' => $vkUserId,
                'message' => $message,
                'photo' => $photo,
            ]));
        }

        return true;
    }

    /**
     * Список userId, которые нужно исключить из Telegram рассылок
     * @return array
     */
    private static function getExcludedTelegramUserIds(): array
    {
        return [
            541,549,558,589,590,609,623,624,632,640,645,659,665,667,675,677,682,683,685,687,704,710,713,767,778,782,794,801,802,808,825,834,835,841,911,914,987,989,991,1001,1012,1038,1055,1059,1060,1074,1100,1109,1110,1116,1120,1123,1124,1151,1153,1160,1170,1182,1194,1195,1217,1237,1250,1253,1254,1256,1260,1300,1325,1338,1374,1379,1382,1409,1426,1440,1451,1495,1497,1526,1540,1543,1545,1559,1560,1564,1565,1567,1568,1572,1574,1577,1586,1589,1596,1597,1604,1622,1631,1638,1678,1679,1685,1690,1699,1717,1733,1735,1745,1751,1752,1768,1777,1791,1794,1800,1804,1810,1822,1833,1841,1849,1853,1859,1871,1874,1885,1889,1891,1893,1921,1928,1934,1951,1954,1955,1960,1963,1964,1965,1987,1990,1995,1997,2004,2005,2019,2022,2027,2029,2034,2049,2060,2063,2069,2072,2076,2077,2081,2085,2087,2088,2103,2105,2113,2120,2122,2128,2139,2150,2154,2155,2157,2165,2172,2189,2192,2204,2222,2232,2240,2254,2259,2268,2271,2285,2287,2289,2294,2295,2296,2309,2324,2332,2343,2348,2351,2353,2355,2365,2367,2374,2379,2402,2404,2408,2412,2418,2421,2428,2453,2467,2471,2473,2505,2507,2512,2514,2520,2526,2547,2550,2570,2571,2576,2583,2589,2594,2599,2602,2610,2614,2619,2629,2635,2636,2637,2651,2652,2666,2680,2690,2700,2705,2728,2741,2747,2748,2749,2750,2752,2765,2804,2806,2818,2862,2868,2896,2902,2911,2916,2926,2927,2928,2931,2937,2939,2940,2948,2950,2951,2952,2956,2958,2959,2964,2983,2986,2990,2993,3004,3012,3013,3015,3041,3043,3051,3068,3073,3076,3080,3088,3092,3095,3097,3102,3112,3118,3139,3147,3150,3161,3164,3172,3174,3178,3179,3187,3196,3202,3205,3208,3211,3215,3221,3226,3232,3233,3249,3272,3277,3281,3286,3301,3305,3307,3322,3341,3348,3352,3355,3357,3371,3372,3376,3381,3391,3397,3398,3411,3415,3421,3433,3456,3458,3459,3465,3468,3469,3475,3478,3484,3489,3490,3509,3528,3535,3540,3541,3543,3560,3568,3591,3607,3617,3628,3636,3640,3649,3658,3665,3667,3669,3699,3702,3719,3720,3723,3745,3752,3755,3757,3759,3777,3792,3798,3808,3810,3815,3830,3832,3843,3852,3873,3881,3885,3889,3891,3912,3916,3917,3941,3948,3951,3954,3955,3965,3979,3988,3989,3992,3998,4018,4025,4030,4047,4049,4051,4059,4083,4106,4108,4113,4114,4118,4122,4123,4134,4142,4157,4163,4166,4174,4181,4183,4187,4191,4235,4244,4317,4324,4334,4339,4355,4356,4357,4358,4375,4380,4385,4397,4411,4415,4421,4425,4430,4431,4447,4477,4498,4509,4555,4569,4588,4632,4646,4670,4671,4689,4711,4739,4767,4780,4791,4793,4816,4829,4836,4852,4924,4946,4952,4995,4999,5008,5058,5111,5121,5167,5188,5214,5244,5278,5299,5308,5380,5402,5419,5445,5460,5492,5560,5561,5570,5617,5657,5682,5737,5738,5740,6022,6182,6261,6316,6335,6386,6392,6421,6429,6465,6497,6508,6564,6604,6793,6844,6848,6883,6894,6954,6957,6963,6967,6974,6992,7061,7069,7098,7130,7135,7136,7306,7319,7323,7328,7341,7359,7400,7425,7436,7438,7460,7465,7475,7480,7483,7485,7520,7547,7567,7572,7645,7733,7768,7788,7797,7807,7810,7849,7966,7982,8033,8171,8179,8201,8212,8220,8349,8420,8532,8602,8608,8641,8675,8830,8838,8844,8849,8850,8923,8949,9158,9202,9300,9360,9427,9437,9474,9501,9509,9533,9623,9648,9762,9783,9803,9834,9838,9840,9863,9912,9929,9993,10020,10043,10060,10175,10194,10317,10379,10386,10421,10433,10437,10468,10471,10491,10495,10580,10620,10676,10694,10715,10752,10786,10807,10857,10877,10886,11033,11160,11169,11261,11274,11275,11281,11343,11393,11492,11606,11611,11644,11650,11663,11710,11843,11866,11872,11901,11925,11930,11933,11947,11994,11995,11996,12009,12035,12049,12074,12091,12105,12107,12122,12217,12218,12262,12272,12298,12361,12399,12414,12479,12535,12709,12715,12721,12740,12754,12769,12818,12845,12963,12970,12980,12984,12991,12995,13014,13045,13053,13059,13116,13120,13160,13203,13206,13217,13233,13253,13440,13499,13525,13540,13588,13729,13747,13757,13838,13849,13885,13939,13981,13987,13991,14027,14107,14132,14153,14210,14273,14284,14300,14303,14305,14307,14320,14337,14384,14385,14391,14400,14450,14485,14567,14631,14645,14684,14703,14705,14739,14782,14838,14872,14885,14897,14912,14925,14962,14968,14983,14984,14987,15030,15070,15078,15080,15082,15105,15179,15180,15221,15249,15269,15270,15271,15274,15293,15318,15359,15373,15380,15434,15459,15468,15536,15550,15581,15606,15618,15619,15654,15665,15738,15740,15753,15758,15777,15815,15824,15857,15860,15902,16000,16006,16216,16222,16320,16375,16419,16470,16503,16538,16554,16650,16658,16665,16674,16683,16695,16716,16865,16887,16998,17046,17071,17085,17091,17099,17158,17160,17275,17327,17391,17407,17428,17541,17607,17654,17679,17687,17715,17760,17767,17848,17882,17919,18024,18075,18083,18119,18132,18152,18188,18208,18306,18422,18431,18505,18560,18564,18592,18608,18623,18641,18650,18667,18679,18690,18703,18721,18726,18777,18792,18811,18827,18840,18861,18872,18894,18923,18937,19007,19097,19101,19143,19163,19164,19169,19202,19215,19237,19266,19313,19329,19330,19341,19415,19426,19428,19455,19463,19465,19473,19557,19563,19590,19648,19658,19676,19731,19742,19768,19770,19772,19802,19819,19838,19896,19902,19911,19946,19979,19984,20027,20071,20105,20150,20154,20161,20177,20179,20193,20208,20232,20240,20309,20310,20313,20323,20384,20393,20396,20491,20501,20502,20505,20506,20525,20538,20539,20584,20600,20618,20636,20641,20642,20646,20662,20684,20706,20727,20779,20785,20819,20840,20848,20853,20854,20857,20909,20922,20966,21035,21044,21114,21118,21124,21141,21153,21159,21164,21165,21166,21187,21193,21198,21209,21246,21248,21282,21292,21302,21306,21310,21317,21346,21363,21377,21406,21518,21535,21603,21621,21662,21696,21704,21707,21724,21747,21761,21762,21779,22002,22076,22224,22232,22240,22268,22290,22292,22323,22355,22358,22378,22391,22419,22432,22444,22493,22499,22521,22525,22537,22555,22560,22563,22614,22645,22682,22714,22734,22753,22772,22790,22845,22856,22906,22941,22966,22998,23001,23028,23059,23064,23080,23099,23108,23115,23146,23152,23174,23182,23183,23208,23249,23348,23384,23438,23442,23447,23456,23467,23481,23513,23545,23555,23580,23595,23634,23639,23648,23659,23671,23678,23691,23692,23732,23739,23752,23772,23790,23839,23848,23852,23853,23866,23891,23935,23941,23994,24022,24029,24065,24074,24081,24103,24270,24275,24311,24339,24341,24348,24362,24367,24392,24406,24410,24491,24554,24559,24572,24586,24590,24603,24626,24630,24651,24656,24671,24747,24835,24836,24841,24845,24848,24884,24918,24984,24987,25026,25069,25077,25133,25149,25225,25226,25254,25282,25319,25325,25330,25347,25352,25362,25363,25378,25398,25430,25445,25447,25453,25493,25525,25527,25599,25622,25645,25667,25702,25739,25740,25756,25779,25790,25802,25803,25816,25829,25884,25890,25899,26011,26017,26022,26040,26051,26055,26113,26135,26145,26149,26165,26171,26191,26199,26203,26210,26318,26340,26351,26384,26392,26409,26534,26543,26546,26642,26692,26696,26703,26722,26744,26746,26758,26779,26788,26791,26807,26819,26820,26832,26834,26882,26907,26919,26934,26935,26955,26960,26962,26969,26981,26990,27026,27027,27040,27053,27059,27073,27113,27126,27133,27180,27302,27335,27345,27348,27351,27357,27376,27396,27410,27495,27510,27525,27533,27541,27553,27559,27586,27730,27739,27762,27771,27781,27792,27806,27847,27856,27860,27866,27905,27931,27934,27935,27959,28030,28036,28042,28088,28100,28107,28129,28172,28188,28233,28261,28266,28267,28288,28331,28353,28417,28426,28452,28453,28467,28484,28529,28550,28595,28614,28658,28699,28701,28703,28734,28766,28885,28900,28934,28952,28953,29113,29117,29224,29243,29244,29248,29258,29271,29325,29335,29338,29366,29375,29376,29381,29386,29393,29429,29447,29451,29460,29473,29482,29484,29502,29532,29535,29544,29611,29670,29704,29756,29776,29801,29818,29839,29944,29980,29990,30096,30114,30142,30156,30158,30176,30193,30223,30232,30253,30271,30285,30289,30307,30311,30322,30324,30327,30329,30375,30447,30524,30540,30551,30576,30687,30781,30789,30826,30829,30830,30834,30845,30914,30972,31007,31040,31076,31078,31089,31154,31250,31272,31280,31291,31304,31340,31355,31365,31371,31432,31433,31460,31538,31555,31560,31565,31580,31605,31608,31611,31615,31638,31657,31663,31765,31803,31805,31905,31981,32020,32038,32056,32491,32499,32532,32674,32679,32709,32729,32768,32822,32827,32840,32843,32844,32848,32898,32983,32986,32994,33064,33081,33084,33116,33125,33147,33211,33242,33246,33247,33248,33299,33329,33346,33419,33429,33430,33445,33454,33463,33481,33495,33496,33531,33543,33544,33563,33575,33601,33604,33605,33626,33652,33677,33708,33724,33759,33763,33781,33845,33865,33919,33952,33982,34005,34034,34048,34091,34102,34168,34187,34217,34222,34225,34279,34281,34290,34300,34308,34321,34322,34391,34401,34419,34495,34540,34550,34571,34580,34596,34738,34760,34770,34800,34840,34888,34893,35043,35136,35170,35240,35262,35272,35281,35310,35316
        ];
    }

    /**
     * Получение аудитории для рассылки
     * @param int $audienceId ID аудитории
     * @param int|null $botId ID бота/платформы (для фильтрации)
     * @param bool $onlyWithUser Отправлять только пользователям с привязанным user (только для VK)
     * @return array
     */
    public static function getAudience($audienceId, $botId = null, $onlyWithUser = false) {
        // Приводим к int для корректного сравнения
        $audienceId = (int)$audienceId;
        $botId = $botId !== null ? (int)$botId : null;
        
        // Фильтрация по платформе
        if ($botId === self::PERSONAL_BOT) {
            // Для Telegram параметр onlyWithUser игнорируется
            $query = User::find()
                ->select('DISTINCT(u.id)')
                ->alias('u')
                ->andWhere(['u.status' => User::STATUS_ACTIVE])
                ->andWhere('telegram_chat_id is NOT NULL')
                ->andWhere(['is_telegram_blocked' => 0]);
            
            // Исключаем userId из списка исключений
            $excludedIds = self::getExcludedTelegramUserIds();
            if (!empty($excludedIds)) {
                $query->andWhere(['NOT IN', 'u.id', $excludedIds]);
            }
            
            if ($audienceId == self::AUDIENCE_TEST) {
                $query->andWhere(['IN', 'u.id', [509]]);
            } elseif ($audienceId == self::AUDIENCE_ALL) {
                // Без дополнительных фильтров
            } elseif ($audienceId == self::AUDIENCE_WINNER) {
                $query->andWhere(['IN', 'steam_id', [76561198161653962]]);
            } elseif ($audienceId == self::AUDIENCE_MODERATORS) {
                // Для Telegram: получаем ID пользователей с ролями ADMIN или MODERATOR
                $adminUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_ADMIN);
                $moderatorUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_MODERATOR);
                $moderatorUserIds = array_merge($adminUserIds, $moderatorUserIds);
                $moderatorUserIds = array_unique($moderatorUserIds);
                
                if (empty($moderatorUserIds)) {
                    return [];
                }
                
                $query->andWhere(['IN', 'u.id', $moderatorUserIds]);
            } else {
                return [];
            }

            $userIds = $query->createCommand()->queryColumn();
            
            // Дополнительная фильтрация после получения результатов (на случай если исключенные ID были в результатах)
            if (!empty($excludedIds) && !empty($userIds)) {
                $userIds = array_diff($userIds, $excludedIds);
            }
            
            return array_values($userIds);
        } elseif ($botId === self::VK_GROUP) {
            // Для VK группы получаем список участников из базы данных (тех, кто разрешил отправку сообщений)
            $vkUsers = VkUser::getUsersWithPermission();
            
            if (empty($vkUsers)) {
                return [];
            }
            
            // Фильтруем по наличию привязанного user, если указано
            if ($onlyWithUser) {
                // Оставляем только тех, у кого есть привязанный user в базе данных
                $vkUsersWithUser = [];
                foreach ($vkUsers as $vkUserId) {
                    $user = User::find()
                        ->where(['vk_id' => $vkUserId])
                        ->exists();
                    if ($user) {
                        $vkUsersWithUser[] = $vkUserId;
                    }
                }
                $vkUsers = $vkUsersWithUser;
            }
            
            // Применяем фильтрацию по аудитории
            if ($audienceId == self::AUDIENCE_TEST) {
                // Для тестовой аудитории берем только первых 5 участников
                return [33610634];
            } elseif ($audienceId == self::AUDIENCE_ALL) {
                // Для всех пользователей возвращаем всех с разрешением
                return $vkUsers;
            } elseif ($audienceId == self::AUDIENCE_WINNER) {
                // Для победителей пока возвращаем всех (можно добавить фильтрацию позже)
                return $vkUsers;
            } elseif ($audienceId == self::AUDIENCE_MODERATORS) {
                // Для VK: получаем ID пользователей с ролями ADMIN или MODERATOR
                $adminUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_ADMIN);
                $moderatorUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_MODERATOR);
                $moderatorUserIds = array_merge($adminUserIds, $moderatorUserIds);
                $moderatorUserIds = array_unique($moderatorUserIds);
                
                if (empty($moderatorUserIds)) {
                    return [];
                }
                
                // Получаем VK ID модераторов и админов (только тех, у кого есть привязанный VK аккаунт)
                $moderatorVkIds = User::find()
                    ->select('vk_id')
                    ->where(['IN', 'id', $moderatorUserIds])
                    ->andWhere(['IS NOT', 'vk_id', null])
                    ->column();
                
                if (empty($moderatorVkIds)) {
                    return [];
                }
                
                // Фильтруем VK пользователей, оставляя только тех, у кого есть привязанный user с ролью модератора/админа
                $filteredVkUsers = array_intersect($vkUsers, $moderatorVkIds);
                
                return array_values($filteredVkUsers);
            }
            
            return [];
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
