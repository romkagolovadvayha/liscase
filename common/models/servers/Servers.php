<?php

namespace common\models\servers;

use common\models\blog\BlogCategory;
use WebSocket\Client;
use Yii;

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
 * @property int    $wargm_id
 * @property string $commands
 * @property string $discord_token
 * @property int    $sort
 * @property string $updated_at
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
            'updated_at'          => Yii::t('common', 'Последнее обновление'),
        ];
    }

    public function rules()
    {
        return [
            [['name', 'status', 'wipe', 'next_wipe', 'global_wipe', 'wipe_type', 'max', 'tag'], 'required'],
            [['description', 'name', 'ip', 'rcon_password', 'commands', 'discord_token', 'rules', 'map', 'tag'], 'string'],
            [['sort', 'status', 'wipe_type', 'port', 'query', 'rcon', 'skindrops', 'team_limit', 'max', 'wargm_id'], 'integer'],
            [['wipe', 'next_wipe', 'global_wipe'], 'safe'],
        ];
    }

    public static function getPlayTime($minutes) {
        $d = floor ($minutes / 1440);
        $h = floor (($minutes - $d * 1440) / 60);
        $m = $minutes - ($d * 1440) - ($h * 60);

        if ($d > 0) {
            return "{$d}д. {$h}ч. {$m}м.";
        } else if ($h > 0) {
            return "{$h}ч. {$m}м.";
        } else {
            return "{$m}м.";
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
        /** @var Servers[] $servers */
        $servers = \common\models\servers\Servers::find()->orderBy(['sort' => SORT_ASC])->all();
        $client = new Client(Yii::$app->params['ws']);
        $total = \common\models\servers\Servers::find()->andWhere(
            ['status' => Servers::STATUS_ACTIVE]
        )->sum('players');

        $serversData = [];
        foreach ($servers as $server) {
            if ($server->players+$server->joined > 0) {
                $percentPlayers = ceil(100/$server->max*$server->players);
                $percentJoined = ceil(100/$server->max*$server->joined);
                $percentQueued = ceil(100/$server->max*$server->queued);
                $percentAbsoluteCount = 100/($percentPlayers+$percentJoined);
                $percentPlayersAbsolute = ceil($percentAbsoluteCount * $percentPlayers);
                $percentJoinedAbsolute = ceil($percentAbsoluteCount * $percentJoined);
                $percentQueuedAbsolute = ceil($percentAbsoluteCount * $percentQueued);
            } else {
                $percentPlayers = 0;
                $percentJoined = 0;
                $percentQueued = 0;
                $percentAbsoluteCount = 0;
                $percentPlayersAbsolute = 0;
                $percentJoinedAbsolute = 0;
                $percentQueuedAbsolute = 0;
            }
            $serversData[] = [
                'server_id' => $server->id,
                'status' => $server->status,
                'players' => $server->players,
                'joined' => $server->joined,
                'queued' => $server->queued,
                'percentPlayers' => $percentPlayers,
                'percentJoined' => $percentJoined,
                'percentQueued' => $percentQueued,
                'percentAbsoluteCount' => $percentAbsoluteCount,
                'percentPlayersAbsolute' => $percentPlayersAbsolute,
                'percentJoinedAbsolute' => $percentJoinedAbsolute,
                'percentQueuedAbsolute' => $percentQueuedAbsolute,
            ];
        }

        try {
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
}
