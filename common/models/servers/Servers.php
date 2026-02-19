<?php

namespace common\models\servers;

use common\models\bans\Bans;
use common\models\blog\BlogCategory;
use common\models\map\Map;
use common\models\profit\Profit;
use common\models\skindrops\Skindrops;
use common\models\signs\Signs;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserRaid;
use common\models\user\UserTop;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "servers".
 *
 * @property int    $id
 * @property string $name
 * @property string $wipe
 * @property int    $wipe_type
 * @property string $next_wipe
 * @property string $global_wipe
 * @property string $description
 * @property string $rules
 * @property string $ip
 * @property string $text_ip
 * @property int    $port
 * @property int    $query
 * @property int    $rcon
 * @property string $rcon_password
 * @property string $map
 * @property int    $players
 * @property int    $joined
 * @property int    $queued
 * @property int    $max
 * @property int    $team_limit
 * @property int    $status
 * @property string $db_host
 * @property string $db_name
 * @property string $db_user
 * @property string $db_password
 * @property string $tag
 * @property bool   $stats_payment
 * @property bool   $skindrops
 * @property bool   $is_store
 * @property bool   $secret_map
 * @property int    $wargm_id
 * @property string $commands
 * @property string $discord_token
 * @property int    $sort
 * @property string $updated_at
 * @property string $monitoring_name
 * @property string $monitoring_description
 * @property string $game_mode
 * @property string $monitoring_tags
 * @property string $wipe_server_name
 * @property string $wipe_server_description
 * @property string $rust_app_id
 * @property int $min_map_size
 * @property int $max_map_size
 * @property int $map_id
 * @property int $map_list_id
 * @property string $secret_key
 *
 * @property Map $mapEntity
 * @property \common\models\map\MapList $mapList
 * @property ServersTagsRelation[] $serversTagsRelations
 * @property ServersTags[] $serversTags
 */
class Servers extends \common\components\base\ActiveRecord
{
    const STATUS_NOACTIVE    = 0;
    const STATUS_ACTIVE  = 1;
    const STATUS_WAIT  = 2;
    const STATUS_CLOSED  = 3;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_NOACTIVE       => Yii::t('common', 'Выключен'),
            self::STATUS_ACTIVE      => Yii::t('common', 'Включен'),
            self::STATUS_WAIT      => Yii::t('common', 'Скоро откроется'),
            self::STATUS_CLOSED      => Yii::t('common', 'Закрыт'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers';
    }
    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'description'               => Yii::t('common', 'Описание'),
            'status'               => Yii::t('common', 'Статус'),
            'wipe'          => Yii::t('common', 'Последний вайп'),
            'next_wipe'          => Yii::t('common', 'Следующий вайп'),
            'global_wipe'          => Yii::t('common', 'Глобальный вайп'),
            'wipe_type'          => Yii::t('common', 'Глобальный вайп'),
            'sort'          => Yii::t('common', 'Сортировка'),
            'ip'          => Yii::t('common', 'IP адрес'),
            'text_ip'     => Yii::t('common', 'Текстовый IP адрес'),
            'port'          => Yii::t('common', 'Порт'),
            'query'          => Yii::t('common', 'Query порт'),
            'rcon'          => Yii::t('common', 'Rcon порт'),
            'rcon_password'          => Yii::t('common', 'Rcon пароль'),
            'commands'          => Yii::t('common', 'Команды доступные на сервере'),
            'skindrops'          => Yii::t('common', 'Раздача скинов'),
            'discord_token'          => Yii::t('common', 'Токен бота Discord'),
            'rules'          => Yii::t('common', 'Правила'),
            'map'          => Yii::t('common', 'Карта на сервере'),
            'team_limit'          => Yii::t('common', 'Лимит команды'),
            'max'          => Yii::t('common', 'Кол-во слотов'),
            'tag'          => Yii::t('common', 'Тег сервера'),
            'wargm_id'          => Yii::t('common', 'WarGM ID'),
            'is_store'          => Yii::t('common', 'Магазин на сервере'),
            'updated_at'          => Yii::t('common', 'Последнее обновление'),
            'monitoring_name'          => Yii::t('common', 'Название сервера в мониторинге'),
            'monitoring_description'          => Yii::t('common', 'Описание сервера в мониторинге'),
            'game_mode'          => Yii::t('common', 'Режим игры'),
            'monitoring_tags'          => Yii::t('common', 'Теги сервера в мониторинге'),
            'wipe_server_name'          => Yii::t('common', 'Название сервера при вайпе (для игры)'),
            'wipe_server_description'          => Yii::t('common', 'Описание сервера при вайпе (для игры)'),
            'rust_app_id'          => Yii::t('common', 'ID в RustApp'),
            'min_map_size'          => Yii::t('common', 'Минимальный размер карты'),
            'max_map_size'          => Yii::t('common', 'Максимальный размер карты'),
            'map_id'          => Yii::t('common', 'ID карты'),
            'map_list_id'          => Yii::t('common', 'ID карты из списка'),
            'secret_key'          => Yii::t('common', 'Секретный ключ'),
            'secret_map'          => Yii::t('common', 'Секретная карты'),
        ];
    }

    public function rules()
    {
        return [
            [['name', 'status', 'wipe', 'next_wipe', 'global_wipe', 'wipe_type', 'max', 'tag', 'monitoring_name', 'monitoring_description', 'min_map_size', 'max_map_size'], 'required'],
            [['description', 'name', 'ip', 'text_ip', 'rcon_password', 'commands', 'discord_token', 'rules', 'map', 'tag', 'monitoring_name', 'monitoring_description', 'game_mode', 'monitoring_tags', 'wipe_server_name', 'wipe_server_description', 'secret_key'], 'string'],
            [['sort', 'status', 'wipe_type', 'port', 'query', 'rcon', 'skindrops', 'is_store', 'team_limit', 'max', 'wargm_id', 'rust_app_id', 'min_map_size', 'max_map_size', 'map_list_id'], 'integer'],
            [['wipe', 'next_wipe', 'global_wipe', 'secret_map'], 'safe'],
            [['tag'], 'unique', 'targetClass' => self::class, 'message' => Yii::t('common', 'Сервер с таким тегом уже существует')],
            [['min_map_size'], 'validateMapSize', 'skipOnError' => false],
            [['wipe'], 'validateWipeDates', 'skipOnError' => false],
            [['next_wipe'], 'validateWipeDates', 'skipOnError' => false],
            [['global_wipe'], 'validateWipeDates', 'skipOnError' => false],
        ];
    }

    /**
     * Валидация размера карты
     * @param string $attribute
     * @param array $params
     */
    public function validateMapSize($attribute, $params)
    {
        if ($this->min_map_size !== null && $this->max_map_size !== null) {
            if ($this->min_map_size > $this->max_map_size) {
                $this->addError('min_map_size', Yii::t('common', 'Минимальный размер карты не может быть больше максимального'));
                $this->addError('max_map_size', Yii::t('common', 'Максимальный размер карты не может быть меньше минимального'));
            }
        }
    }

    /**
     * Валидация дат вайпов
     * @param string $attribute
     * @param array $params
     */
    public function validateWipeDates($attribute, $params)
    {
        // Проверяем все даты, даже если валидируется только одно поле
        // Это нужно, чтобы валидация срабатывала при изменении любой даты
        
        if (empty($this->wipe) || empty($this->next_wipe) || empty($this->global_wipe)) {
            return; // Пропускаем если даты не заполнены (required валидация сработает)
        }

        try {
            $wipe = new \DateTime($this->wipe);
            $nextWipe = new \DateTime($this->next_wipe);
            $globalWipe = new \DateTime($this->global_wipe);

            // Проверяем порядок дат: wipe <= next_wipe <= global_wipe
            if ($wipe > $nextWipe) {
                $this->addError('wipe', Yii::t('common', 'Дата последнего вайпа не может быть позже даты следующего вайпа'));
                $this->addError('next_wipe', Yii::t('common', 'Дата следующего вайпа не может быть раньше даты последнего вайпа'));
            }

            if ($nextWipe > $globalWipe) {
                $this->addError('next_wipe', Yii::t('common', 'Дата следующего вайпа не может быть позже даты глобального вайпа'));
                $this->addError('global_wipe', Yii::t('common', 'Дата глобального вайпа не может быть раньше даты следующего вайпа'));
            }

            if ($wipe > $globalWipe) {
                $this->addError('wipe', Yii::t('common', 'Дата последнего вайпа не может быть позже даты глобального вайпа'));
                $this->addError('global_wipe', Yii::t('common', 'Дата глобального вайпа не может быть раньше даты последнего вайпа'));
            }
        } catch (\Exception $e) {
            // Если дата невалидна, required валидация или другой валидатор обработает это
            Yii::warning('Ошибка парсинга даты вайпа: ' . $e->getMessage(), __METHOD__);
        }
    }
    
    /**
     * Переопределяем beforeSave для дополнительной валидации перед сохранением
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        
        // Дополнительная проверка валидации перед сохранением
        // Вызываем валидацию явно для всех полей
        if (!$this->validate(null, false)) {
            $errors = $this->getErrors();
            Yii::warning('Модель не прошла валидацию перед сохранением. Ошибки: ' . json_encode($errors, JSON_UNESCAPED_UNICODE), __METHOD__);
            Yii::warning('Атрибуты модели: ' . json_encode($this->attributes, JSON_UNESCAPED_UNICODE), __METHOD__);
            return false;
        }
        
        // Дополнительная проверка дат и размеров карты
        $this->validateMapSize('min_map_size', []);
        $this->validateWipeDates('wipe', []);
        
        // Проверка на NULL для полей, которые могут вызвать проблемы
        // Преобразуем пустые строки в NULL для числовых полей, которые могут быть NULL
        if ($this->team_limit === '') {
            $this->team_limit = null;
        }
        if ($this->wargm_id === '') {
            $this->wargm_id = null;
        }
        if ($this->rust_app_id === '') {
            $this->rust_app_id = null;
        }
        if ($this->map_list_id === '') {
            $this->map_list_id = null;
        }
        
        // Убеждаемся, что числовые поля имеют правильный тип
        if ($this->min_map_size !== null) {
            $this->min_map_size = (int)$this->min_map_size;
        }
        if ($this->max_map_size !== null) {
            $this->max_map_size = (int)$this->max_map_size;
        }
        if ($this->status !== null) {
            $this->status = (int)$this->status;
        }
        if ($this->wipe_type !== null) {
            $this->wipe_type = (int)$this->wipe_type;
        }
        if ($this->max !== null) {
            $this->max = (int)$this->max;
        }
        
        if ($this->hasErrors()) {
            Yii::warning('Обнаружены ошибки после дополнительной валидации: ' . json_encode($this->getErrors(), JSON_UNESCAPED_UNICODE), __METHOD__);
            return false;
        }
        
        return true;
    }

    public static function getPlayTime($minutes) {
        $d = floor ($minutes / 1440);
        $h = floor (($minutes - $d * 1440) / 60);
        $m = $minutes - ($d * 1440) - ($h * 60);

        $dName = Yii::t('common', 'день');
        if (in_array($d, [2,3,4])) {
            $dName = Yii::t('common', 'дня');
        } elseif ($d > 4) {
            $dName = Yii::t('common', 'дней');
        }

        $hName = Yii::t('common', 'час');
        if (in_array($h, [2,3,4,22,23,24])) {
            $hName = Yii::t('common', 'часа');
        } elseif (in_array($h, [1,21])) {
            $hName = Yii::t('common', 'час');
        } elseif ($h > 4) {
            $hName = Yii::t('common', 'часов');
        }

        $mName = Yii::t('common', 'минута');
        if (in_array($m, [2,3,4,22,23,24])) {
            $mName = Yii::t('common', 'минуты');
        } elseif (in_array($m, [1,21])) {
            $mName = Yii::t('common', 'минута');
        } elseif ($m > 4) {
            $mName = Yii::t('common', 'минут');
        }

        if ($d > 0) {
            return "{$d} {$dName} {$h} {$hName}";
        } else if ($h > 0) {
            return "{$h} {$hName} {$m} {$mName}";
        } else {
            return "{$m} {$mName}";
        }
    }

    public function updateDbConfig() {
        Yii::$app->db_server->username = $this->db_user;
        Yii::$app->db_server->password = $this->db_password;
        Yii::$app->db_server->dsn = "mysql:host={$this->db_host};dbname={$this->db_name}";
    }
    /**
     * @return array
     */
    public static function getServers() {
        /** @var Servers[] $models */
        $models = Servers::find()->andWhere(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all();

        $result = [];
        foreach ($models as $model) {
            $result[$model->tag] = $model->name;
        }

        return $result;
    }

    public static function notify() {
        try {
            /** @var Servers[] $servers */
            $servers = \common\models\servers\Servers::find()->orderBy(['sort' => SORT_ASC])->all();
            $projectStats = Statistics::projectStats();
            $total = $projectStats['online'];

            $serversData = [];
            foreach ($servers as $server) {
                $serversData[] = [
                    'server_id' => $server->id,
                    'status' => $server->status,
                    'players' => $server->players,
                    'joined' => $server->joined,
                    'queued' => $server->queued,
                ];
                $serversData = ArrayHelper::merge($serversData, $server->monitoring());
            }

            // Используем новый метод через кеш вместо создания WebSocket клиента
            // Это избегает rate limiting т.к. не создаются новые подключения
            \console\controllers\ChatServer::broadcastOnlineUpdate($serversData, $total);
            
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('Servers notify: ' . $ex->getMessage());
        }
    }

    public function monitoring() {
        $result = [];
        if ($this->players + $this->joined > 0) {
            $result['percentPlayers']         = ceil(100 / $this->max * $this->players);
            $result['percentJoined']          = ceil(100 / $this->max * $this->joined);
            $result['percentQueued']          = ceil(100 / $this->max * $this->queued);
            $result['percentAbsoluteCount']   = 100 / ($result['percentPlayers'] + $result['percentJoined']);
            $result['percentPlayersAbsolute'] = ceil($result['percentAbsoluteCount'] * $result['percentPlayers']);
            $result['percentJoinedAbsolute']  = ceil($result['percentAbsoluteCount'] * $result['percentJoined']);
            $result['percentQueuedAbsolute']  = ceil($result['percentAbsoluteCount'] * $result['percentQueued']);
        } else {
            $result['percentPlayers']         = 0;
            $result['percentJoined']          = 0;
            $result['percentQueued']           = 0;
            $result['percentAbsoluteCount']   = 0;
            $result['percentPlayersAbsolute'] = 0;
            $result['percentJoinedAbsolute']  = 0;
            $result['percentQueuedAbsolute']  = 0;
        }

        return $result;
    }

    public function wipeTypeText() {
        if ($this->wipe_type === 7) {
            return Yii::t('common', 'Еженедельно');
        }
        if ($this->wipe_type === 14) {
            return Yii::t('common', 'Каждые две недели');
        }
        if ($this->wipe_type === 30) {
            return Yii::t('common', 'Раз в месяц');
        }
        return null;
    }

    public function wipeTime() {
      return strtotime($this->wipe);
    }

    public function nextWipeTime() {
      return strtotime($this->next_wipe);
    }

    public function globalWipeTime() {
      return strtotime($this->global_wipe);
    }

    public function currentWipe() {
      return (new \DateTime($this->wipe))->format('Y-m-d') . "/" . (new \DateTime($this->next_wipe))->format('Y-m-d');
    }

    public function getLink($key, $steamId = null) {
        if ($key === 'rules') {
            return "/servers/{$this->tag}/rules";
        }
        if ($key === 'stats') {
            return "/servers/{$this->tag}";
        }
        if ($key === 'user-stats') {
            return "/servers/{$this->tag}/{$steamId}";
        }
        if ($key === 'wipe-info') {
            return "/servers/{$this->tag}/wipe-info";
        }
        if ($key === 'maps') {
            return "/maps/{$this->tag}";
        }
        if ($key === 'map') {
            return "https://rustmaps.com/map/{$this->map}";
        }
        return null;
    }

    /**
     * @param int $i
     *
     * @return User|null
     * @throws \Exception
     */
    public function getWinner($i = 0, $all = false) {
        if ($i > 4) {
            return null;
        }

        if (!$all) {
            $usersDroped = Skindrops::find()
                                ->select('DISTINCT(steam_id)')
                                ->andWhere(['>', 'created_at', date('Y-m-d 00:00:01')])
                                ->createCommand()
                                ->queryColumn();
        } else {
            $usersDroped = [];
        }

        $prefix = strtolower(Yii::$app->settings->get('skindrops_prefix'));
        /** @var User $_user */
        $_user = User::find()
                    ->andWhere(['>=', 'last_visit_server_at', date('Y-m-d H:i:s', time() - 5 * 60)])
                    ->andWhere(['server_id' => $this->id])
                    ->andWhere(['status' => User::STATUS_ACTIVE])
                    ->andWhere(['NOT IN', 'steam_id', $usersDroped])
                    ->andWhere(['LIKE', 'username', '%' . $prefix . '%', false])
                    ->orderBy('rand()')
                    ->one();

        if (empty($_user)) {
            $_user = User::find()
                         ->andWhere(['>=', 'last_visit_server_at', date('Y-m-d H:i:s', time() - 5 * 60)])
                         ->andWhere(['server_id' => $this->id])
                         ->andWhere(['status' => User::STATUS_ACTIVE])
                         ->andWhere(['LIKE', 'username', '%' . $prefix . '%', false])
                         ->orderBy('rand()')
                         ->one();
        }

        if (empty($_user)) {
            return null;
        }

        $user = User::findBySteamId($_user->steam_id, false, 'servers');

        if (strpos(mb_strtolower($user->username), $prefix) === false) {
            return $this->getWinner($i + 1, $all);
        }

        return $user;
    }

    public function goDraw() {
        $minOnline = Yii::$app->settings->get('skindrops_minOnline');
        if ($this->players < $minOnline || $this->status !== Servers::STATUS_ACTIVE) {
            \Yii::$app->telegramChats->sendMessage("Минимальный онлайн: {$minOnline}\nТекущий онлайн: {$this->players}\nСервер: {$this->name}\nРозыгрыш отменен!");
            return;
        }
        $winner = $this->getWinner();
        if (empty($winner)) {
            $winner = $this->getWinner(0, true);
        }
        if (empty($winner)) {
            \Yii::$app->telegramChats->sendMessage("\nСервер: {$this->name}\nПобедитель не найден!");
            return;
        }

        $skin = null;
        $items = Yii::$app->rustTm->items();
        shuffle($items);
        foreach ($items as $item) {
            $minSum = Yii::$app->settings->get('skindrops_minSum');
            $maxSum = Yii::$app->settings->get('skindrops_maxSum');
            if ($item['price'] < $minSum) {
                continue;
            }
            if ($item['price'] > $maxSum) {
                continue;
            }
            $skin = $item;
            break;
        }

        $price = $skin['price'];
        $name = $skin['name'];
        $image = $skin['image'];
        $image300 = $skin['image300'];

        $model = new Skindrops();
        $model->name = $name;
        $model->steam_id = $winner->steam_id;
        $model->player = $winner->username;
        $model->price = ceil($price);
        $model->real_price = ceil($price);
        $model->image = $image;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);

        $model = new Profit();
        $model->user_balance_id = $winner->getSkinsBalance()->id;
        $model->amount = ceil($price);
        $model->comment = Yii::t('common', 'Выйгрыш скина');
        $model->status = 1;
        $model->type = Profit::TYPE_WINNER_SKINS;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        $winner->getSkinsBalance()->recalculateBalance();

        $winner->sendChatWinnerMessage($price, $name, '', $this);
    }

    /**
     * Gets query for [[MapEntity]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMapEntity()
    {
        return $this->hasOne(Map::class, ['id' => 'map_id']);
    }

    /**
     * Gets query for [[MapList]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMapList()
    {
        return $this->hasOne(\common\models\map\MapList::class, ['id' => 'map_list_id']);
    }

    /**
     * Gets query for [[ServersTagsRelations]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServersTagsRelations()
    {
        return $this->hasMany(ServersTagsRelation::class, ['server_id' => 'id']);
    }

    /**
     * Gets query for [[ServersTags]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServersTags()
    {
        return $this->hasMany(ServersTags::class, ['id' => 'tag_id'])
            ->viaTable('servers_tags_relation', ['server_id' => 'id'])
            ->andWhere(['status' => ServersTags::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC]);
    }

    /**
     * Получить ID тегов сервера
     * @return array
     */
    public function getTagIds()
    {
        return ServersTagsRelation::find()
            ->select('tag_id')
            ->where(['server_id' => $this->id])
            ->column();
    }

    public function calculateTop()
    {
        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['>=', 'last_visit_server_at', date('Y-m-d H:i:s', time() - 5 * 60)])
                     ->andWhere(['status' => User::STATUS_ACTIVE])
                     ->andWhere(['server_id' => $this->id])
                     ->andWhere(['is_stats' => true])
                     ->orderBy(['last_visit_server_at' => SORT_DESC])
                     ->all();

        echo "count users: " . count($users) . PHP_EOL;

        $userIds = [];
        $steamIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->id;
            $steamIds[] = $user->steam_id;
        }

        $keys = UserTop::getRaitingKeys();

        /** @var Statistics[] $rawData */
        $rawData = Statistics::find()
                             ->select(['steam_id', 'key', 'value'])
                             ->andWhere(['server_tag' => $this->tag])
                             ->andWhere(['IN', 'steam_id', $steamIds])
                             ->andWhere(['IN', 'key', $keys])
                             ->andWhere(['wipe' => $this->currentWipe()])
                             ->asArray()
                             ->all();

        $stats = [];
        foreach ($rawData as $row) {
            $steamId = $row['steam_id'];
            $key = $row['key'];
            $value = $row['value'];

            // Инициализируем массив, если его еще нет
            if (!isset($stats[$steamId])) {
                $stats[$steamId] = [];
            }
            if (!isset($stats[$steamId][$key])) {
                $stats[$steamId][$key] = 0;
            }

            // Заполняем данные
            $stats[$steamId][$key] = $value;
        }

        echo "count stats: " . count($stats) . PHP_EOL;

        /** @var UserTop[] $userTopsData */
        $userTopsData = UserTop::find()
                          ->andWhere(['server_id' => $this->id])
                          ->andWhere(['IN', 'user_id', $userIds])
                          ->andWhere(['wipe' => $this->currentWipe()])
                          ->all();

        $userTops = [];
        foreach ($userTopsData as $userTop) {
            if (empty($userTops[$userTop->user_id])) {
                $userTops[$userTop->user_id] = [];
            }
            $userTops[$userTop->user_id][$userTop->key] = $userTop;
        }
        echo "count tops: " . count($userTops) . PHP_EOL;

        foreach ($users as $user) {
            if (empty($stats[$user->steam_id])) {
                continue;
            }
            $userStat = $stats[$user->steam_id];
            $user->calculateTop($userStat, $userTops, $this);
        }
    }

    public function getWipes($update = false)
    {
        $cacheKey = 'Servers_getWipes_' . $this->id;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];
        $wipes = Statistics::find()
                  ->select('DISTINCT(wipe)')
                  ->andWhere(['server_tag' => $this->tag])
                  ->orderBy(['id' => SORT_DESC])
                  ->createCommand()
                  ->queryColumn();

        $count = 0;
        foreach ($wipes as $wipe) {
            $result[$wipe] = $wipe;
            $count++;
            if ($count > 10) {
                break;
            }
        }

        Yii::$app->cache->set($cacheKey, $result, 30*24*60*60); // 30 дней
        return $result;
    }

    /**
     * Получение метрики из кэша или вычисление с последующим кэшированием
     * @param string $metricKey Ключ метрики
     * @param callable $callback Функция для вычисления метрики
     * @param bool $forceUpdate Принудительное обновление кэша
     * @return mixed
     */
    private function getCachedMetric(string $metricKey, callable $callback, bool $forceUpdate = false)
    {
        $cacheKey = 'Servers_metric_' . $metricKey . '_' . $this->id;
        $cached = false;
        if (!$forceUpdate) {
            $cached = Yii::$app->cache->get($cacheKey);
        }
        if ($cached === false) {
            $cached = $callback();
            Yii::$app->cache->set($cacheKey, $cached, 30 * 24 * 60 * 60); // 30 дней
        }
        return $cached;
    }

    /**
     * Обновление кэша всех метрик сервера
     * @return int Количество обновленных метрик
     */
    public function refreshMetricsCache(): int
    {
        $metrics = $this->getServerMetrics(true);
        return count($metrics);
    }

    /**
     * Получение метрик сервера (топ-3 по различным показателям)
     * @param bool $forceUpdate Принудительное обновление кэша
     * @return array
     */
    public function getServerMetrics(bool $forceUpdate = false): array
    {
        $metrics = [];
        
        // Обертка для передачи $forceUpdate в getCachedMetric
        $server = $this;
        $getCached = function($key, $callback) use ($forceUpdate, $server) {
            return $server->getCachedMetric($key, $callback, $forceUpdate);
        };

        // 1. Кто больше всего отправлял репортов
        $metrics['top_reporters'] = $getCached('top_reporters', function() {
            $topReporters = Reports::find()
                ->select(['steam_id', 'COUNT(*) as count'])
                ->where(['server_tag' => $this->tag])
                ->groupBy('steam_id')
                ->orderBy(['count' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topReporters, 'count');
        });

        // 2. На кого больше всего отправляли репортов
        $metrics['top_reported'] = $getCached('top_reported', function() {
            $topReported = Reports::find()
                ->select(['recepient_steam_id as steam_id', 'COUNT(*) as count'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IS NOT', 'recepient_steam_id', null])
                ->groupBy('recepient_steam_id')
                ->orderBy(['count' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topReported, 'count');
        });

        // 3. Кто больше всех взорвал ракет (сумма всех типов ракет)
        $metrics['top_rockets'] = $getCached('top_rockets', function() {
            $rocketKeys = ['rocket_basic', 'rocket_basic_rpg', 'rocket_hv', 'rocket_hv_rpg', 'rocket_fire', 'rocket_fire_rpg'];
            $topRockets = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IN', 'key', $rocketKeys])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topRockets, 'total');
        });

        // 4. Кто больше всех убил игроков
        $metrics['top_killers'] = $getCached('top_killers', function() {
            $topKillers = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'kills'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topKillers, 'total');
        });

        // 5. Кто больше всех добыл серы
        $metrics['top_sulfur'] = $getCached('top_sulfur', function() {
            $topSulfur = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'sulfur.ore'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topSulfur, 'total');
        });

        // 6. Кто больше всех провел времени на сервере
        $metrics['top_playtime'] = $getCached('top_playtime', function() {
            $topPlaytime = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'playtime'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            
            // Конвертируем секунды в минуты
            foreach ($topPlaytime as &$item) {
                $item['total'] = round($item['total'] / 60);
            }
            unset($item);
            
            return $this->formatTopMetrics($topPlaytime, 'total');
        });

        // 7. Кто больше всех открыл ящиков
        $metrics['top_boxes'] = $getCached('top_boxes', function() {
            $topBoxes = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'crate_open'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topBoxes, 'total');
        });

        // 8. Кто больше всех разбил бочек
        $metrics['top_barrels'] = $getCached('top_barrels', function() {
            $topBarrels = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'barrel'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topBarrels, 'total');
        });

        // 9. Кто больше всех добыл дерева
        $metrics['top_wood'] = $getCached('top_wood', function() {
            $topWood = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'wood'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topWood, 'total');
        });

        // 10. Кто больше всех добыл металла
        $metrics['top_metal'] = $getCached('top_metal', function() {
            $topMetal = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'metal.ore'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topMetal, 'total');
        });

        // 11. Кто больше всех добыл камня
        $metrics['top_stone'] = $getCached('top_stone', function() {
            $topStone = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'stones'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topStone, 'total');
        });

        // 12. Кто больше всех погиб (deaths)
        $metrics['top_deaths'] = $getCached('top_deaths', function() {
            $topDeaths = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'deaths'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topDeaths, 'total');
        });

        // 13. Кто больше всех убил ученых
        $metrics['top_scientists'] = $getCached('top_scientists', function() {
            $topScientists = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'scientists'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topScientists, 'total');
        });

        // 14. Кто больше всех взорвал C4
        $metrics['top_c4'] = $getCached('top_c4', function() {
            $topC4 = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'c4thrown'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topC4, 'total');
        });

        // 15. Кто больше всех взорвал сатчелей
        $metrics['top_satchels'] = $getCached('top_satchels', function() {
            $topSatchels = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'satchelsthrown'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topSatchels, 'total');
        });

        // 16. Кто больше всех использовал гранат F1
        $metrics['top_grenades_f1'] = $getCached('top_grenades_f1', function() {
            $topGrenadesF1 = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'grenade.f1.deployed'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topGrenadesF1, 'total');
        });

        // 17. Кто больше всех использовал коктейли Молотова
        $metrics['top_molotov'] = $getCached('top_molotov', function() {
            $topMolotov = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'grenade.molotov.deployed'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topMolotov, 'total');
        });

        // 18. Кто больше всех убил животных (сумма всех типов)
        $metrics['top_animals'] = $getCached('top_animals', function() {
            $animalKeys = ['chicken', 'bear', 'boar', 'polarbear', 'deer', 'horse', 'wolf2', 'wolf'];
            $topAnimals = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IN', 'key', $animalKeys])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topAnimals, 'total');
        });

        // 19. Кто больше всех поймал рыбы
        $metrics['top_fish'] = $getCached('top_fish', function() {
            $fishKeys = [
                'f_fish.anchovy', 'f_fish.catfish', 'f_fish.herring', 'f_fish.orangeroughy',
                'f_fish.salmon', 'f_fish.sardine', 'f_fish.smallshark', 'f_fish.troutsmall',
                'f_fish.yellowperch'
            ];
            $topFish = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IN', 'key', $fishKeys])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topFish, 'total');
        });

        // 20. Кто больше всех собрал ягод (сумма всех типов)
        $metrics['top_berries'] = $getCached('top_berries', function() {
            $berryKeys = [
                'gathered_green.berry', 'gathered_blue.berry', 'gathered_yellow.berry',
                'gathered_red.berry', 'gathered_white.berry'
            ];
            $topBerries = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IN', 'key', $berryKeys])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topBerries, 'total');
        });

        // 21. Кто больше всех собрал ткани
        $metrics['top_cloth'] = $getCached('top_cloth', function() {
            $topCloth = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'gathered_cloth'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topCloth, 'total');
        });

        // 22. Кто больше всех добыл скрапа
        $metrics['top_scrap'] = $getCached('top_scrap', function() {
            $topScrap = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'scrap'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topScrap, 'total');
        });

        // 23. Кто больше всех добыл животного жира
        $metrics['top_fat'] = $getCached('top_fat', function() {
            $topFat = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'fat.animal'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topFat, 'total');
        });

        // 24. Кто больше всех собрал тыкв
        $metrics['top_pumpkin'] = $getCached('top_pumpkin', function() {
            $topPumpkin = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'gathered_pumpkin'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topPumpkin, 'total');
        });

        // 23. Кто больше всех собрал кукурузы
        $metrics['top_corn'] = $getCached('top_corn', function() {
            $topCorn = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'gathered_corn'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topCorn, 'total');
        });

        // 24. Кто больше всех собрал картофеля
        $metrics['top_potato'] = $getCached('top_potato', function() {
            $topPotato = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'gathered_potato'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topPotato, 'total');
        });

        // 25. Кто больше всех убил медведей
        $metrics['top_bears'] = $getCached('top_bears', function() {
            $topBears = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'bear'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topBears, 'total');
        });

        // 26. Кто больше всех убил кабанов
        $metrics['top_boars'] = $getCached('top_boars', function() {
            $topBoars = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'boar'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topBoars, 'total');
        });

        // 27. Кто больше всех убил оленей
        $metrics['top_deer'] = $getCached('top_deer', function() {
            $topDeer = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'deer'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topDeer, 'total');
        });

        // 28. Кто больше всех убил волков
        $metrics['top_wolves'] = $getCached('top_wolves', function() {
            $wolfKeys = ['wolf', 'wolf2'];
            $topWolves = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IN', 'key', $wolfKeys])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topWolves, 'total');
        });

        // 29. Кто больше всех рейдил шкафов
        $metrics['top_cupboard_raids'] = $getCached('top_cupboard_raids', function() {
            $topCupboardRaids = UserRaid::find()
                ->select(['user_id', 'COUNT(*) as total'])
                ->where(['server_id' => $this->id, 'type' => 'cupboard'])
                ->groupBy('user_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            
            if (empty($topCupboardRaids)) {
                return [];
            }
            
            // Батч-загрузка пользователей
            $userIds = array_column($topCupboardRaids, 'user_id');
            $users = User::find()
                ->where(['IN', 'id', $userIds])
                ->indexBy('id')
                ->all();
            
            $topCupboardRaidsFormatted = [];
            foreach ($topCupboardRaids as $raid) {
                if (!isset($users[$raid['user_id']])) {
                    continue;
                }
                $user = $users[$raid['user_id']];
                $topCupboardRaidsFormatted[] = [
                    'steam_id' => $user->steam_id,
                    'total' => $raid['total']
                ];
            }
            
            return $this->formatTopMetrics($topCupboardRaidsFormatted, 'total');
        });

        // 30. Максимальная дистанция убийства (топ-3)
        $metrics['top_kill_distance'] = $getCached('top_kill_distance', function() {
            $topKillDistance = Kills::find()
                ->select(['steam_id', 'MAX(CAST(distance AS DECIMAL(10,2))) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['!=', 'distance', ''])
                ->andWhere(['IS NOT', 'distance', null])
                ->andWhere(['type' => 'kill'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            
            foreach ($topKillDistance as &$item) {
                $item['total'] = round($item['total']);
            }
            unset($item);
            
            return $this->formatTopMetrics($topKillDistance, 'total');
        });

        // 31. Кто больше всех убил через Kills таблицу (точное количество)
        $metrics['top_kills_exact'] = $getCached('top_kills_exact', function() {
            $topKillsExact = Kills::find()
                ->select(['steam_id', 'COUNT(*) as total'])
                ->where(['server_tag' => $this->tag, 'type' => 'kill'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topKillsExact, 'total');
        });

        // 32. Кто больше всех убил ученых через Kills таблицу
        $metrics['top_scientists_kills'] = $getCached('top_scientists_kills', function() {
            $topScientistsKills = Kills::find()
                ->select(['steam_id', 'COUNT(*) as total'])
                ->where(['server_tag' => $this->tag, 'type' => 'scientists'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topScientistsKills, 'total');
        });

        // 33. Кто больше всех использовал взрывчатых боеприпасов
        $metrics['top_explosive_ammo'] = $getCached('top_explosive_ammo', function() {
            $topExplosiveAmmo = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'ammo_explosive'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topExplosiveAmmo, 'total');
        });

        // 34. Кто больше всех провел вайпов
        $metrics['top_wipes'] = $getCached('top_wipes', function() {
            $topWipes = Statistics::find()
                ->select(['steam_id', 'COUNT(DISTINCT wipe) as total'])
                ->where(['server_tag' => $this->tag])
                ->andWhere(['IS NOT', 'wipe', null])
                ->andWhere(['!=', 'wipe', ''])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topWipes, 'total');
        });

        // 35. Кто больше всех использовал красные карты (card_level_3)
        $metrics['top_red_cards'] = $getCached('top_red_cards', function() {
            $topRedCards = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'card_level_3'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topRedCards, 'total');
        });

        // 36. Кто больше всех использовал зеленые карты (card_level_2)
        $metrics['top_green_cards'] = $getCached('top_green_cards', function() {
            $topGreenCards = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'card_level_2'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topGreenCards, 'total');
        });

        // 37. Кто больше всех использовал синие карты (card_level_1)
        $metrics['top_blue_cards'] = $getCached('top_blue_cards', function() {
            $topBlueCards = Statistics::find()
                ->select(['steam_id', 'SUM(value) as total'])
                ->where(['server_tag' => $this->tag, 'key' => 'card_level_1'])
                ->groupBy('steam_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            return $this->formatTopMetrics($topBlueCards, 'total');
        });

        // 38. Кто больше всех создал табличек
        $metrics['top_signs'] = $getCached('top_signs', function() {
            $topSigns = Signs::find()
                ->select(['user_id', 'COUNT(*) as total'])
                ->where(['server_id' => $this->id])
                ->groupBy('user_id')
                ->orderBy(['total' => SORT_DESC])
                ->limit(3)
                ->asArray()
                ->all();
            
            if (empty($topSigns)) {
                return [];
            }
            
            // Батч-загрузка пользователей
            $userIds = array_column($topSigns, 'user_id');
            $users = User::find()
                ->where(['IN', 'id', $userIds])
                ->indexBy('id')
                ->all();
            
            $topSignsFormatted = [];
            foreach ($topSigns as $sign) {
                if (!isset($users[$sign['user_id']])) {
                    continue;
                }
                $user = $users[$sign['user_id']];
                $topSignsFormatted[] = [
                    'steam_id' => $user->steam_id,
                    'total' => $sign['total']
                ];
            }
            
            return $this->formatTopMetrics($topSignsFormatted, 'total');
        });

        // Общее количество выданных банов
        $metrics['total_bans'] = $getCached('total_bans', function() {
            return Bans::find()
                ->count();
        });

        return $metrics;
    }

    /**
     * Форматирование топ-метрик с добавлением информации о пользователе
     * @param array $data Данные из запроса
     * @param string $valueKey Ключ для значения
     * @return array
     */
    private function formatTopMetrics(array $data, string $valueKey): array
    {
        if (empty($data)) {
            return [];
        }
        
        // Собираем все steam_id для батч-загрузки
        $steamIds = [];
        foreach ($data as $item) {
            if (!empty($item['steam_id'])) {
                $steamIds[] = $item['steam_id'];
            }
        }
        
        if (empty($steamIds)) {
            return [];
        }
        
        // Загружаем всех пользователей одним запросом
        $users = User::find()
            ->where(['IN', 'steam_id', $steamIds])
            ->indexBy('steam_id')
            ->all();
        
        $result = [];
        foreach ($data as $item) {
            if (empty($item['steam_id']) || !isset($users[$item['steam_id']])) {
                continue;
            }
            
            $user = $users[$item['steam_id']];
            $result[] = [
                'steam_id' => $item['steam_id'],
                'username' => $user->username,
                'avatar' => $user->getAvatar(),
                'value' => (int)$item[$valueKey],
            ];
        }
        
        return $result;
    }

}
