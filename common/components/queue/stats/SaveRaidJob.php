<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\components\queue\telegram\SendMessageJob;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\team\Team;
use common\models\user\User;
use common\models\user\UserRaid;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveRaidJob extends BaseObject implements JobInterface
{
    public $data;
    public $serverTag;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $request = json_decode($this->data, 1);
            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['tag' => $this->serverTag])
                             ->one();
            if (empty($server)) {
                return;
            }
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            if (!empty($request['raids'])) {
                foreach ($request['raids'] as $item) {
                    try {
                        $steamId = $request['steam_id'];
                        $user = User::findBySteamId($steamId);
                        $location = $request['entityLocation'];
                        $explosives = $request['explosiveUsed'];
                        $owners = $request['owners'];
                        $createdAt = $request['created_at'];

                        $model = new UserRaid();
                        $model->user_id = $user->id;
                        $model->location = $location;
                        $model->explosives = json_encode($explosives);
                        $model->owners = json_encode($owners);
                        $model->created_at = $createdAt;
                        $model->server_id = $server->id;
                        $model->wipe = $wipeDate;
                        $model->save();

                        /** @var User[] $users */
                        $users = User::find()
                            ->andWhere(['IN', 'steam_id', $owners])
                            ->all();

                        foreach ($users as $owner) {
                            if ($owner->raid_notify) {
                                $message = "⚠️ <b>Внимание!</b> Ваша постройка в квадрате {$location} атакована!";
                                if (!empty($explosives)) {
                                    $message .= "⚠️ Для нанесения урона было использовано: ";

                                    $keys = [];
                                    foreach ($explosives as $key) {
                                        $keys[] = str_replace('.deployed', '', $key);
                                    }
                                    $drops = \common\models\box\Drop::find()
                                                                    ->cache(60*60)
                                                                    ->andWhere(['IN', 'eng_name', $keys])
                                                                    ->indexBy('eng_name')
                                                                    ->all();
                                    $names = [];
                                    foreach ($explosives as $explosive) {
                                        $key = str_replace('.deployed', '', $explosive);
                                        if (empty($drops[$key])) {
                                            continue;
                                        }
                                        $names[] = $drops[$key]->name;
                                    }

                                    $message .= implode(',', $names) . ".";
                                }
                                Yii::$app->queueTelegram->push(new SendMessageJob([
                                    'telegram_chat_id' => $owner->telegram_chat_id,
                                    'message' => $message,
                                    'buttons' => [],
                                ]));
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::error("SaveRaidJob foreach: " . $e->getLine() . ":" . $e->getMessage(), 'error');
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("SaveRaidJob: " . $e->getLine() . ":" . $e->getMessage(), 'error');
        }
    }
}