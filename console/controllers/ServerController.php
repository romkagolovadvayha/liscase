<?php
namespace console\controllers;

use common\models\invoice\Deposit;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserTop;
use consik\yii2websocket\WebSocketServer;
use console\daemons\Battle;
use GPBMetadata\Google\Type\Datetime;
use Ratchet\App;
use yii\base\BaseObject;
use yii\console\Controller;

class ServerController extends Controller
{
    /**
     * server/check-gamer
     */
    public function actionCheckGamer($group = true) {
        $limit = 50;
        if (!$group) {
            $limit = 1000;
        }

        /** @var Statistics[] $statistics */
        $statistics = Statistics::find()
            ->alias('s')
            ->joinWith(['user u'])
            ->andWhere(['u.is_gamer' => 0])
            ->andWhere(['s.key' => 'playtime'])
            ->andWhere(['>=', 's.value', 90])
            ->limit($limit)
            ->all();

        echo "count: " . count($statistics) . PHP_EOL;
        $count = 0;
        foreach ($statistics as $model) {
            $model->user->is_gamer = 1;
            if ($model->user->save(false)) {
                $count++;
                if ($group) {
                    $command = "o.usergroup add \"{$model->user->steam_id}\" gamer";
                    RconTasks::execute($command);
                }
                /** @var user[] $_users */
                $_users = User::find()
                              ->andWhere(['steam_id' => $model->user->steam_id])
                              ->all();
                if (count($_users) > 1) {
                    foreach ($_users as $_user) {
                        if ($model->user->id == $_user->id) {
                            continue;
                        }
                        $_user->is_gamer = 1;
                        $_user->save(false);
                    }
                }
            } else {
                if (!empty($model->user->getErrors())) {
                    print_r($model->user->getErrors());
                    exit;
                }
            }
        }
        echo "is_gamer: " . $count . PHP_EOL;
    }

    /**
     * server/check-status
     */
    public function actionCheckStatus() {
        /** @var Servers $server */
        $servers = Servers::find()
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->all();

        foreach ($servers as $server) {
            if (time() - strtotime($server->updated_at) > 185) {
                $server->status = Servers::STATUS_NOACTIVE;
                $server->save();
            }
        }
    }

    /**
     * server/report
     */
    public function actionReport() {
        $date = new \DateTime();
        $date->modify('-1 day');
        /** @var Deposit[] $deposits */
        $deposits = Deposit::find()
                          ->andWhere(['status' => Deposit::STATUS_SUCCESS])
                          ->andWhere(['>=', 'created_at', $date->format('Y-m-d 00:00:00')])
                          ->andWhere(['<=', 'created_at', $date->format('Y-m-d 23:59:59')])
                          ->all();

        $result = [];
        $total = 0;
        foreach ($deposits as $deposit) {
            $total += $deposit->amount;
            if (empty($deposit->user->server)) {
                continue;
            }
            if (empty($result[$deposit->user->server_id])) {
                $result[$deposit->user->server_id] = [
                    'amount' => 0,
                    'server_name' => $deposit->user->server->name,
                ];
            }
            $result[$deposit->user->server_id]['amount'] += $deposit->amount;
        }

        $message = "💰️ <b>Отчет по поступлениям за {$date->format('d.m.Y')}</b>" . PHP_EOL . PHP_EOL;

        foreach ($result as $item) {
            $amount = number_format($item['amount'], 0, '.', ' ');
            $message .= "<i>{$item['server_name']}</i>" . PHP_EOL;
            $message .= "Сумма: {$amount} RUB" . PHP_EOL . PHP_EOL;
        }

        $totalStr = number_format($total, 0, '.', ' ');
        $message .= "Всего: {$totalStr} RUB";

        \Yii::$app->telegramReport->sendMessage($message);
    }

    /**
     * server/recalculate-top
     */
    public function actionRecalculateTop() {
        ini_set('memory_limit', '512M');
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            /** @var Wipe[] $models */
            $statistics = Statistics::find()
                                    ->cache(3*60)
                                    ->andWhere(['server_tag' => $server->tag])
                                    ->andWhere(['wipe' => $wipeDate])
                                    ->asArray()
                                    ->all();

            $userList = [];
            foreach ($statistics as $item) {
                $userList[$item['steam_id']][$item['key']] = $item['value'];
            }

            $steamIds = array_keys($userList);
            $tops = [
                'kills' => 'Киллер',
                'scientists' => 'Мирный',
                'hunter' => 'Охотник',
                'fermer' => 'Фермер',
                'farmer' => 'Фармер',
                'fishing' => 'Рыбак',
                'playtime' => 'Онлайн',
                'reider' => 'Рейдер',
            ];
            foreach ($steamIds as $_steamId) {
                $params = $userList[$_steamId];
                $user = User::findBySteamId($_steamId);
                foreach ($tops as $type => $value) {
                    /** @var UserTop $userTop */
                    $userTop = UserTop::find()
                                      ->andWhere(['user_id' => $user->id])
                                      ->andWhere(['key' => $type])
                                      ->andWhere(['server_id' => $server->id])
                                      ->andWhere(['wipe' => $wipeDate])
                                      ->one();

                    if (empty($userTop)) {
                        $userTop = new UserTop();
                        $userTop->user_id = $user->id;
                        $userTop->key = $type;
                        $userTop->value = 0;
                        $userTop->server_id = $server->id;
                        $userTop->wipe = $wipeDate;
                    }

                    foreach (UserTop::getRaiting()[$type] as $k => $v) {
                        $userTop->value += Statistics::getParam($params, $k) * $v;
                    }

                    if ($userTop->key != 'kills' && $userTop->value < 10) {
                        continue;
                    }

                    $userTop->save();
                }
            }
        }
    }
}