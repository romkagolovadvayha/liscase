<?php

namespace common\models\stats;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property int    $players
 * @property int    $joined
 * @property int    $queued
 * @property string $updated_at
 */
class Info extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'main_stats_info';
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return Yii::$app->db_server;
    }

    /**
     * @param Servers $server
     *
     * @return Info|\yii\db\ActiveRecord|null
     */
    public static function getInfo($server) {
//        $server->updateDbConfig();
        Info::getDb()->username = $server->db_user;
        Info::getDb()->password = $server->db_password;
        Info::getDb()->dsn = "mysql:host={$server->db_host};dbname={$server->db_name}";
        Info::getDb()->pdo = null;
        return Info::find()
                      ->andWhere(['id' => 1])
                      ->one();
    }
}
