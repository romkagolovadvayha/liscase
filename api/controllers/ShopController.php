<?php

namespace api\controllers;

use common\components\queue\process\ActivatedDropJob;
use common\controllers\WebController;
use common\models\box\Drop;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\site\SiteSetting;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\box\DropBlocked;
use WebSocket\Client;
use yii\base\BaseObject;
use yii\web\Controller;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class ShopController extends Controller
{

     /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

//{
//"result": "fail",
//"message": "\u0418\u0433\u0440\u043e\u043a \u043d\u0435 \u0437\u0430\u0440\u0435\u0433\u0438\u0441\u0442\u0440\u0438\u0440\u043e\u0432\u0430\u043d",
//"code": 105
//}
    public function actionIndex($secret, $method, $steam_id = null, $item_id = null, $id = null) {
        header('Content-type: application/json');
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(60)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        /** @var Servers $server */
        $server = null;
        foreach ($servers as $_server) {
            if ($_server->secret_key == $secret) {
                $server = $_server;
                break;
            }
        }
        if (empty($server)) {
            return json_encode([
                                   'result' => 'fail',
                                   'message' => 'Магазин с таким ID и SecretKey не найден',
                                   'code' => 103,
                               ]);
        }

        if ($method === 'basket') {
            return json_encode($this->methodBasket($steam_id, $server->id),JSON_PRETTY_PRINT);
        }
        if ($method === 'take') {
            return json_encode($this->methodTake($item_id),JSON_PRETTY_PRINT);
        }
        if ($method === 'item') {
            return json_encode($this->methodItem($id, $steam_id),JSON_PRETTY_PRINT);
        }
        if ($method === 'gived') {
            return json_encode($this->methodGived($id, $server),JSON_PRETTY_PRINT);
        }
        if ($method === 'basket.commands.instant') {
            return json_encode($this->methodInstant(),JSON_PRETTY_PRINT);
        }
        if ($method === 'info') {
            return json_encode($this->methodInfo(),JSON_PRETTY_PRINT);
        }

        return json_encode([
                               'result' => 'fail',
                               'message' => 'Метод не найден!',
                               'code' => 105,
                           ],JSON_PRETTY_PRINT);
    }

    /**
     *
     * @return array
     */
    private function methodInfo() {
        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        $result['data'] = [
            'link' => Yii::$app->settings->get('site_domain'),
            'default_balance' => 50,
        ];
        return $result;
    }

    /**
     * @param      $item_id
     * @param Servers $server
     *
     * @return array
     */
    private function methodGived($item_id, $server = null) {
        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($item_id);
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return [
                'result' => 'fail',
                'message' => "Предмет уже получен/продан",
                'code' => 107,
            ];
        }
//        $gangRustIds = [-742865266, -1843426638, 1248356124, -1878475007, -1321651331];
//        if (in_array($userDrop->drop[0]->rust_id, $gangRustIds)) {
//            $dropBlockedIds = Drop::find()
//                                  ->select('DISTINCT(id)')
//                                  ->andWhere(['IN', 'rust_id', $gangRustIds])
//                                  ->createCommand()
//                                  ->queryColumn();
//            $dateSendend = (new \DateTime())->modify('-1 minute')->format('Y-m-d H:i:s');
//            $exist = UserDrop::find()
//                             ->andWhere(['status' => UserDrop::STATUS_SENDED])
//                             ->andWhere(['user_id' => $userDrop->user_id])
//                             ->andWhere(['IN', 'drop_id', $dropBlockedIds])
//                             ->andWhere(['>=', 'sended_at', $dateSendend])
//                             ->exists();
//            if ($exist) {
//                return [
//                    'result' => 'fail',
//                    'message' => "КД на взрывчатку, попробуйте позже",
//                    'code' => 107,
//                ];
//            }
//        }

        $userDrop->sended_at = date('Y-m-d H:i:s');
        $userDrop->status = UserDrop::STATUS_SENDED;

        if (!empty($server) && !empty($userDrop->drop[0]->dropStat)) {
            $steamId = $userDrop->user->steam_id;
            $statistics = Statistics::find()
                                    ->andWhere(['steam_id' => $steamId])
                                    ->andWhere(['server_tag' => $server->tag])
                                    ->andWhere(['wipe' => $server->currentWipe()])
                                    ->indexBy('key')
                                    ->all();

            foreach ($userDrop->drop[0]->dropStat as $dropStat) {
                if (empty($dropStat->value)) {
                    continue;
                }
                if (!empty($statistics[$dropStat->stat_key])) {
                    $statistics[$dropStat->stat_key]->value += $userDrop->count * $dropStat->value;
                    $statistics[$dropStat->stat_key]->save();
                } else {
                    $model = new Statistics();
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->key = $dropStat->stat_key;
                    $model->value = $userDrop->count * $dropStat->value;
                    $model->wipe = $server->currentWipe();
                    $model->save();
                }
            }
        }

        // Обработка VIP товара
        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id] ?? null;
        if ($drop && $drop->drop_type === Drop::TYPE_VIP) {
            // Проверяем, есть ли у сервера магазин (is_store = 1)
            // Если сервер без доната, VIP уже выдан в методе give(), команда не выполняется
            if ($server && $server->is_store == 1) {
                // VIP всегда выдается на месяц (30 дней)
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                \common\models\user\UserVip::createOrExtend($userDrop->user_id, $expiresAt);

                $user = $userDrop->user;
                if ($user) {
                    $template = trim((string) $drop->command) !== ''
                        ? (string) $drop->command
                        : Drop::VIP_STORE_RCON_DEFAULT;
                    $command = str_replace('%STEAMID%', $user->steam_id, $template);
                    \common\models\rcon\RconTasks::execute($command);
                }
            }
            // Если сервер без доната (is_store = 0), ничего не делаем - VIP уже выдан в методе give()
        }

        \Yii::$app->queueProcess->push(new ActivatedDropJob(['userDrop'  => $userDrop]));

        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        return $result;
    }

    /**
     *
     * @return array
     */
    private function methodItem($item_id, $steam_id = null) {
        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($item_id);
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return [
                'result' => 'fail',
                'message' => "Предмет уже получен/продан",
                'code' => 107,
            ];
        }

        if (!empty($steam_id) && $steam_id != $userDrop->user->steam_id) {
            return [
                'result' => 'fail',
                'message' => "Товар вам не принадлежит!",
                'code' => 107,
            ];
        }
        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id];

        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        $item = [
            'id' => $userDrop->id,
            'amount' => $userDrop->count,
            'name' => $drop->name,
            'lvl_inspection' => 0,
            'full_only' => $drop->full_only,
            'is_blocked_building' => $drop->is_blocked_building,
            'subDrop' => [],
        ];
        if ($drop->full_only) {
            foreach ($drop->subDrops as $subDrop) {
                $_subDrop = [];
                $_subDrop['count'] = $subDrop->count;
                if (!empty($subDrop->drop->command)) {
                    $_subDrop['command'] = str_replace("\r", '', $subDrop->drop->command);
                    $_subDrop['type'] = "command";
                    $_subDrop['item_id'] = 0;
                } else {
                    $_subDrop['type'] = "item";
                    $_subDrop['item_id'] = $subDrop->drop->rust_id;
                }
                $item['subDrop'][] = $_subDrop;
            }
        }
        if (!empty($drop->command)) {
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;
        } else {
            $item['type'] = "item";
            $item['item_id'] = $drop->rust_id;
        }
        $result['data'] = $item;
        return $result;
    }

    /**
     *
     * @return array
     */
    private function methodTake($item_id) {
        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($item_id);
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return [
                'result' => 'fail',
                'message' => "Предмет уже получен/продан",
                'code' => 107,
            ];
        }

        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id];
        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        $item = [
            'id' => $userDrop->id,
            'amount' => $userDrop->count,
            'name' => $drop->name,
            'lvl_inspection' => 0,
        ];
        if (!empty($drop->command)) {
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;
        } else {
            $item['type'] = "item";
            $item['item_id'] = $drop->rust_id;
        }
        $result['data'] = $item;
        return $result;
    }

    /**
     *
     * @return array
     */
    private function methodBasket($steam_id, $serverId) {
        /** @var User $user */
        $user = User::find()
                    ->andWhere(['steam_id' => $steam_id])
                    ->one();

        if (empty($user)) {
            return [
                'result' => 'fail',
                'message' => 'Игрок не зарегистрирован',
                'code' => 105,
            ];
        }

        /** @var UserDrop[] $userDrops */
        $userDrops = $user->getUserDrop()
                          ->andWhere(['IN', 'status', [UserDrop::STATUS_ACTIVE, UserDrop::STATUS_WAIT]])
                          ->orderBy(['id' => SORT_DESC])
                          ->all();

        $result = [];
        $data = [];
        $images = Drop::productsImages();
        $drops = Drop::getDropListAll();
        $itemsBlocked = DropBlocked::getBlockedList($serverId);
        foreach ($userDrops as $userDrop) {
            $drop = $drops[$userDrop->drop_id];
            $item = [
                'id' => $userDrop->id,
                'amount' => $userDrop->count,
                'name' => $drop->name,
                'img' => $images[$userDrop->drop_id]['64px'],
                'blocked' => false,
                'block_date' => null,
                'kd' => false,
                'full_only' => $drop->full_only,
                'is_blocked_building' => $drop->is_blocked_building,
                'subDrop' => [],
            ];
            if (!empty($drop->blocked_hour)) {
                if (!empty($itemsBlocked[$userDrop->drop_id])) {
                    $item['blocked'] = true;
                    $item['block_date'] = strtotime($itemsBlocked[$userDrop->drop_id]);
                }
            }
//            $gangRustIds = [-742865266, -1843426638, 1248356124, -1878475007, -1321651331];
//            if (in_array($userDrop->drop[0]->rust_id, $gangRustIds)) {
//                $dropBlockedIds = Drop::find()
//                                      ->select('DISTINCT(id)')
//                                      ->andWhere(['IN', 'rust_id', $gangRustIds])
//                                      ->createCommand()
//                                      ->queryColumn();
//                $dateSendend = (new \DateTime())->modify('-1 minute')->format('Y-m-d H:i:s');
//                /** @var UserDrop $dropBlock */
//                $dropBlock = UserDrop::find()
//                                 ->andWhere(['status' => UserDrop::STATUS_SENDED])
//                                 ->andWhere(['user_id' => $userDrop->user_id])
//                                 ->andWhere(['IN', 'drop_id', $dropBlockedIds])
//                                 ->andWhere(['>=', 'sended_at', $dateSendend])
//                                 ->one();
//                if (!empty($dropBlock)) {
//                    $endBlockedDate = (new \DateTime($dropBlock->sended_at))->modify('+1 minute')->format('Y-m-d H:i:s');
//                    $item['blocked'] = true;
//                    $item['block_date'] = strtotime($endBlockedDate);
//                    $item['kd'] = true;
//                }
//            }
            if ($drop->full_only) {
                foreach ($drop->subDrops as $subDrop) {
                    $_subDrop = [];
                    if (!empty($subDrop->drop->command)) {
                        $_subDrop['command'] = str_replace("\r", '', $subDrop->drop->command);
                        $_subDrop['type'] = "command";
                        $_subDrop['item_id'] = 0;
                    } else {
                        $_subDrop['type'] = "item";
                        $_subDrop['item_id'] = $subDrop->drop->rust_id;
                    }
                    $item['subDrop'][] = $_subDrop;
                }
            }
            if (!empty($drop->command)) {
                $item['command'] = str_replace("\r", '', $drop->command);
                $item['type'] = "command";
                $item['item_id'] = 0;
            } else {
                $item['type'] = "item";
                $item['item_id'] = $drop->rust_id;
            }
            $data[] = $item;
        }
        $result['result'] = "success";
        $result['code'] = 100;
        $result['data'] = $data;
        return $result;
    }

    /**
     * @param User $user
     *
     * @return array
     */
    private function methodInstant() {
        $result = [];
        $result['message'] = "Данных нет";
        $result['result'] = "fail";
        $result['code'] = 104;
        return $result;
    }
}
