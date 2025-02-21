<?php

namespace common\models\servers;

use common\models\blog\BlogCategory;
use common\models\map\Map;
use common\models\profit\Profit;
use common\models\skindrops\Skindrops;
use common\models\statistics\Statistics;
use common\models\user\User;
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
 * @property int    $wargm_id
 * @property string $commands
 * @property string $discord_token
 * @property int    $sort
 * @property string $updated_at
 * @property string $monitoring_name
 * @property string $monitoring_description
 * @property string $rust_app_id
 * @property int $min_map_size
 * @property int $max_map_size
 * @property int $map_id
 * @property string $secret_key
 *
 * @property Map $mapEntity
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
            'monitoring_name'          => Yii::t('common', 'Название в мониторинге'),
            'monitoring_description'          => Yii::t('common', 'Доп. название в мониторинге'),
            'rust_app_id'          => Yii::t('common', 'ID в RustApp'),
            'min_map_size'          => Yii::t('common', 'Минимальный размер карты'),
            'max_map_size'          => Yii::t('common', 'Максимальный размер карты'),
            'map_id'          => Yii::t('common', 'ID карты'),
            'secret_key'          => Yii::t('common', 'Секретный ключ'),
        ];
    }

    public function rules()
    {
        return [
            [['name', 'status', 'wipe', 'next_wipe', 'global_wipe', 'wipe_type', 'max', 'tag', 'monitoring_name', 'monitoring_description', 'min_map_size', 'max_map_size'], 'required'],
            [['description', 'name', 'ip', 'rcon_password', 'commands', 'discord_token', 'rules', 'map', 'tag', 'monitoring_name', 'monitoring_description', 'secret_key'], 'string'],
            [['sort', 'status', 'wipe_type', 'port', 'query', 'rcon', 'skindrops', 'is_store', 'team_limit', 'max', 'wargm_id', 'rust_app_id', 'min_map_size', 'max_map_size'], 'integer'],
            [['wipe', 'next_wipe', 'global_wipe'], 'safe'],
        ];
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
            $client = new Client(Yii::$app->params['ws']);
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

            $client->send(
                json_encode(
                    [
                        'action' => 'updatedOnline',
                        'code' => 200,
                        'total' => $total,
                        'servers' => $serversData,
                    ]
                )
            );
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

        $user = User::findBySteamId($_user->steam_id, true);

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

    public function calculateTop()
    {
        $date = new \DateTime();
        $date->modify('-30 day');
        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['>=', 'last_visit_server_at', $date->format('Y-m-d H:i:s')])
                     ->andWhere(['status' => User::STATUS_ACTIVE])
                     ->andWhere(['server_id' => $this->id])
                     ->andWhere(['is_stats' => true])
                     ->orderBy(['last_visit_server_at' => SORT_DESC])
                     ->all();

        $keys = UserTop::getRaitingKeys();

        /** @var Statistics[] $rawData */
        $rawData = Statistics::find()
                             ->select(['steam_id', 'key', 'value'])
                             ->andWhere(['server_tag' => $this->tag])
                             ->andWhere(['IN', 'key', $keys])
                             ->andWhere(['wipe' => $this->currentWipe()])
                             ->asArray()
                             ->all();

        foreach ($users as $user) {
            $user->calculateTop();
        }
    }
}
