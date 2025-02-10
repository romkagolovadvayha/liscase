<?php

namespace common\models\rcon;

use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "rcon_tasks".
 * Чекает каждые 30 секунд команды на сервере и выполняет их
 *
 * @property int    $id
 * @property string $command
 * @property int    $status
 * @property string $server_tag
 * @property string $created_at
 */
class RconTasks extends \common\components\base\ActiveRecord
{
    const STATUS_WAIT = 0;
    const STATUS_DONE = 1;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_WAIT       => Yii::t('common', 'В ожидании'),
            self::STATUS_DONE      => Yii::t('common', 'Выполнено'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rcon_tasks';
    }
    /**
     * {@inheritdoc}
     */
    public static function execute($command, $serversCommand = [])
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()->cache(30)->andWhere(['status' => Servers::STATUS_ACTIVE])->all();
        foreach ($servers as $server) {
            if (!empty($serversCommand) && !in_array($server->tag, $serversCommand)) {
                continue;
            }
            $rconTask = new RconTasks();
            $rconTask->status = RconTasks::STATUS_WAIT;
            $rconTask->command = $command;
            $rconTask->server_tag = $server->tag;
            $rconTask->created_at = date('Y-m-d H:i:s');
            $rconTask->save();
        }
    }

}
