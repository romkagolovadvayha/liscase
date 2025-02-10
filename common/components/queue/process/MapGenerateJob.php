<?php

namespace common\components\queue\process;

use common\models\map\Map;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class MapGenerateJob extends BaseObject implements JobInterface
{
    public $serverId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            /** @var Servers $server */
            $server = Servers::findOne($this->serverId);

            $sizes = [4250, 3750];

            /** @var Map[] $maps */
            $maps = Map::find()
                       ->andWhere(['server_id' => $server->id])
                       ->all();
            foreach ($maps as $map) {
                $map->archived();
            }
            $countSizes = 0;
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                $countSizes++;
            }
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                Map::actionParsing($size, $server->id, (int)(10 / $countSizes));
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("MapGenerateJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }

}