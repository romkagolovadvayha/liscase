<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\bans\Bans;
use common\models\invoice\Deposit;
use common\models\servers\Servers;
use Yii;
use yii\base\Component;

class RustApp
{

    private $_banList = [];

    public function banList() {
        $rustAppApiKey = 'af376656-3f99-4db6-9b74-8e07741b76c2';
        //$servers = $this->servers();
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $serverList = [];
        foreach ($servers as $server) {
            $serverList[$server->rust_app_id] = $server->id;
        }

        self::serverBans($serverList, "Простой", 0, $rustAppApiKey);

        return $this->_banList;
    }

    private function serverBans($serverList, $projectName, $page, $rustAppApiKey) {
        $bans = $this->serverList($page, $rustAppApiKey);
        if (empty($bans)) {
            return [];
        }
        foreach ($bans as $item) {
            $banDate = null;
            $expireDate = null;
            if (!empty($item['unban_time'])) {
                $expireDate = date('Y-m-d H:i:s', $item['unban_time'] / 1000);
            }
            if (!empty($item['created_at'])) {
                $banDate = date('Y-m-d H:i:s', $item['created_at'] / 1000);
            }
            $serverId = null;
            if (!empty($item['server_ids'])) {
                $exist = false;
                foreach ($item['server_ids'] as $rustAppId) {
                    if (!empty($serverList[$rustAppId])) {
                        $exist = true;
                        $serverId = $serverList[$rustAppId];
                        break;
                    }
                }
                if (!$exist) {
                    continue;
                }
            }
            /** @var Bans $model */
            $model = Bans::find()
                         ->andWhere(['steam_id' => $item['steam_id']])
                         ->andWhere(['server_id' => $serverId])
                         ->one();

            if (!empty($model)) {
                if (empty($model->unbanned_at) && !empty($expireDate)) {
                    $model->unbanned_at = $expireDate;
                    $model->save();
                } elseif (!empty($model->unbanned_at) && empty($expireDate)) {
                    $model->unbanned_at = null;
                    $model->save();
                }
                continue;
            }

            $this->_banList[] = [
                'username' => $item['player']['steam_name'],
                'steam_id' => $item['steam_id'],
                'reason' => $item['reason'],
                'ip' => $item['ban_ip'],
                'date' => $banDate,
                'expireDate' => $expireDate,
                'server_id' => $serverId,
                'project' => $projectName,
            ];
        }
        self::serverBans($serverList, $projectName, $page + 1, $rustAppApiKey);
    }

    private function serverList($page, $rustAppApiKey) {
        try {
            $apiUrl = "https://court.rustapp.io/public/bans?sort_by=created&page=" . $page;
            $curl = clone Yii::$app->curl;
            $curl->setHeader('accept', 'application/json');
            $curl->setHeader('x-api-key', $rustAppApiKey);
            return json_decode($curl->get($apiUrl), 1)['results'];
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage($e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getMessage());
        }
        return [];
    }

    private function servers($rustAppApiKey) {
        try {
            $apiUrl = "https://court.rustapp.io/public/servers";
            $curl = clone Yii::$app->curl;
            $curl->setHeader('accept', 'application/json');
            $curl->setHeader('x-api-key', $rustAppApiKey);
            $response = json_decode($curl->get($apiUrl), 1);
            $result = [];
            foreach ($response as $item) {
                $result[$item['id']] = $item;
            }
            return $result;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage($e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getMessage());
        }
        return [];
    }

}
