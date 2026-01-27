<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\team\Team;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class UpdateStatsUsersJob extends BaseObject implements JobInterface
{
    public $users;
    public $serverTag;
    /** @var Servers */
    public $server;
    public $wipeDate;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            foreach ($this->users as $steamId => $params) {
                if (empty($params)) {
                    continue;
                }
                $wipeDate = $this->wipeDate;
                $statistics = Statistics::find()
                                        ->andWhere(['steam_id' => $steamId])
                                        ->andWhere(['server_tag' => $this->serverTag])
                                        ->andWhere(['wipe' => $wipeDate])
                                        ->indexBy('key')
                                        ->all();

                unset($params['kills']);
                unset($params['deaths']);

                // Проверяем наличие cupboard_authorized для отправки уведомления
                $hasCupboardAuth = !empty($params['cupboard_authorized']);

                $dbTransaction = Yii::$app->db->beginTransaction();
                try {
                    foreach ($params as $key => $value) {
                        if (empty($value)) {
                            continue;
                        }
                        if (!empty($statistics[$key])) {
                            $statistics[$key]->value += $value;
                            $statistics[$key]->save();
                        } else {
                            $model = new Statistics();
                            $model->steam_id = $steamId;
                            $model->server_tag = $this->serverTag;
                            $model->key = $key;
                            $model->value = $value;
                            $model->wipe = $wipeDate;
                            $model->save();
                        }
                    }
                    $dbTransaction->commit();
                    Yii::$app->queueClansStats->push(new SaveStatsClansJob([
                                                                               'params' => $params,
                                                                               'wipe' => $wipeDate,
                                                                               'server' => $this->server,
                                                                               'steamId' => $steamId,
                                                                           ]));

                    // Отправляем уведомление если игрок авторизовался в шкафу
                    if ($hasCupboardAuth) {
                        $this->sendRaidNotifyPromo($steamId);
                    }
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("UpdateStatsUsersJob::updateTop(user, key, value, server, wipeDate): " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    $dbTransaction->rollBack();
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateStatsUsersJob::updateTop(user, key, value, server, wipeDate): " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }

    /**
     * Отправляет уведомление пользователю о подключении Telegram бота
     *
     * @param string $steamId Steam ID пользователя
     * @return void
     */
    protected function sendRaidNotifyPromo($steamId)
    {
        try {
            // Находим пользователя (без создания нового)
            $user = User::find()->where(['steam_id' => $steamId])->one();
            if (!$user) {
                return;
            }

            // Находим сервер
            $server = Servers::findOne(['tag' => $this->serverTag, 'status' => Servers::STATUS_ACTIVE]);
            if (!$server) {
                return;
            }

            // Проверяем нужно ли отправлять уведомление
            // (если бот не подключен или оповещения отключены)
            if (empty($user->telegram_chat_id) || $user->is_telegram_blocked || !$user->raid_notify) {
                // Отправляем сообщение в игровой чат
                $user->sendRaidNotifyPromoMessage($server);
            }
        } catch (\Exception $e) {
            Yii::error("Failed to send raid notify promo: " . $e->getMessage(), __METHOD__);
        }
    }
}
