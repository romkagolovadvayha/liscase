<?php

namespace common\models\servers;

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
 */
class Servers extends \common\components\base\ActiveRecord
{
    const STATUS_NOACTIVE    = 0;
    const STATUS_ACTIVE  = 1;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_NOACTIVE       => Yii::t('common', 'Выключен'),
            self::STATUS_ACTIVE      => Yii::t('common', 'Включен'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers';
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

}
