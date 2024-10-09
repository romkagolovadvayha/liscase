<?php

namespace common\models\bansystem;

use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "ban_list".
 *
 * @property int    $id
 * @property string $steam_id
 * @property string $project_name
 * @property string $server_name
 * @property string $reason
 * @property string $banned_at
 * @property string $unbanned_at
 *
 */
class BanList extends \common\components\base\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'ban_list';
    }

    /**
     * @param $steamId
     * @param $projectName
     * @param $serverName
     * @param $reason
     * @param $bannedAt
     * @param $unbannedAt
     *
     * @return bool
     */
    public static function createModel($steamId, $projectName, $serverName, $reason, $bannedAt, $unbannedAt)
    {
        $model = self::findOne([
                'steam_id' => $steamId,
                'project_name' => $projectName,
        ]);
        if (!empty($model)) {
            return true;
        }

        $model = new self();
        $model->steam_id = $steamId;
        $model->project_name = $projectName;
        $model->server_name = $serverName;
        $model->reason = $reason;
        $model->banned_at = $bannedAt;
        $model->unbanned_at = $unbannedAt;

        return $model->save();
    }
}
