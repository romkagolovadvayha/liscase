<?php

namespace common\components\queue\stats;

use common\components\queue\telegram\SendMessageJob;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserRaid;
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
                        $steamId = $item['steam_id'];
                        $user = User::findBySteamId($steamId);
                        $location = $item['entityLocation'];
                        $explosives = $item['explosiveUsed'];
                        $owners = $item['owners'];
                        $createdAt = $item['created_at'];

                        $model = new UserRaid();
                        $model->user_id = $user->id;
                        $model->location = $location;
                        $model->explosives = json_encode($explosives);
                        $model->owners = json_encode($owners);
                        $model->created_at = $createdAt;
                        $model->server_id = $server->id;
                        $model->wipe = $wipeDate;

                        if (!empty($model->getErrors())) {
                            Yii::$app->telegramChats->sendMessage("SaveRaidJob save UserRaid: " . json_encode($model->getErrors()));
                        }

                        /** @var User[] $users */
                        $users = User::find()
                            ->andWhere(['IN', 'steam_id', $owners])
                            ->andWhere(['raid_notify' => 1])
                            ->all();

                        if (!empty($users)) {
                            $message = "⚠️ <b>Внимание!</b> Ваша постройка в квадрате {$location} атакована!" . PHP_EOL . PHP_EOL;
                            $message .= "Сервер: {$server->name}";
                            if (!empty($explosives)) {
                                $keys = [];
                                foreach ($explosives as $key) {
                                    if ($key === 'explosive.satchel.deployed') {
                                        $key = 'satchelsthrown';
                                    }
                                    if ($key === 'explosive.timed.deployed') {
                                        $key = 'c4thrown';
                                    }
                                    $keys[] = str_replace('.deployed', '', $key);
                                }
                                $drops = \common\models\box\Drop::find()
                                                                ->cache(60*60)
                                                                ->andWhere(['IN', 'eng_name', $keys])
                                                                ->indexBy('eng_name')
                                                                ->all();
                                $names = [];
                                foreach ($drops as $drop) {
                                    $names[] = $drop->name;
                                }

                                if (!empty($names)) {
                                    $message .= PHP_EOL . "Для нанесения урона было использовано: " . implode(',', $names) . ".";
                                }
                            }
                            $model->notify = 1;
                            Yii::$app->telegramChats->sendMessage($message);
                            foreach ($users as $owner) {
                                Yii::$app->queueTelegram->push(new SendMessageJob([
                                    'telegram_chat_id' => $owner->telegram_chat_id,
                                    'message' => $message,
                                    'buttons' => [],
                                ]));
                            }
                        }
                        $model->save(false);
                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage($this->data);
                        Yii::$app->telegramChats->sendMessage("SaveRaidJob foreach: " . $e->getLine() . ":" . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage($this->data);
            Yii::$app->telegramChats->sendMessage("SaveRaidJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }
}