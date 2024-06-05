<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserDrop;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class ApiController extends WebController
{
    CONST secretKey = '79f57ce93708fdbd05b57f6e48154737';

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
//        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
//        \Yii::$app->response->formatters = [
//            Response::FORMAT_JSON => [
//                'class' => JsonResponseFormatter::class,
//                'prettyPrint' => true
//            ]
//        ];
        if (self::secretKey !== $secret) {
            return [
                'result' => 'fail',
                'message' => 'Магазин с таким ID и SecretKey не найден',
                'code' => 103,
            ];
        }

        if ($method === 'basket') {
            return json_encode($this->methodBasket($steam_id),JSON_PRETTY_PRINT);
        }
        if ($method === 'take') {
            return json_encode($this->methodTake($item_id),JSON_PRETTY_PRINT);
        }
        if ($method === 'item') {
            return json_encode($this->methodItem($id),JSON_PRETTY_PRINT);
        }
        if ($method === 'gived') {
            return json_encode($this->methodGived($id),JSON_PRETTY_PRINT);
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
            'link' => 'prostoj.store',
            'default_balance' => 50,
        ];
        return $result;
    }

    /**
     *
     * @return array
     */
    private function methodGived($item_id) {
        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($item_id);
        if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
            return [
                'result' => 'fail',
                'message' => "Предмет уже получен/продан",
                'code' => 107,
            ];
        }

        $userDrop->status = UserDrop::STATUS_SENDED;
        $userDrop->save();

        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        return $result;
    }

    /**
     *
     * @return array
     */
    private function methodItem($item_id) {
        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($item_id);
        if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
            return [
                'result' => 'fail',
                'message' => "Предмет уже получен/продан",
                'code' => 107,
            ];
        }

        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        $item = [
            'id' => $userDrop->id,
            'amount' => $userDrop->count,
            'name' => $userDrop->drop[0]->name,
            'lvl_inspection' => 0,
        ];
        if (!empty($userDrop->drop[0]->command)) {
            $item['command'] = str_replace("\r", '', $userDrop->drop[0]->command);
            $item['type'] = "command";
            $item['item_id'] = 0;
        } else {
            $item['type'] = "item";
            $item['item_id'] = $userDrop->drop[0]->rust_id;
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
        if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
            return [
                'result' => 'fail',
                'message' => "Предмет уже получен/продан",
                'code' => 107,
            ];
        }
        $result = [];
        $result['result'] = "success";
        $result['code'] = 100;
        $item = [
            'id' => $userDrop->id,
            'amount' => $userDrop->count,
            'name' => $userDrop->drop[0]->name,
            'lvl_inspection' => 0,
        ];
        if (!empty($userDrop->drop[0]->command)) {
            $item['command'] = str_replace("\r", '', $userDrop->drop[0]->command);
            $item['type'] = "command";
            $item['item_id'] = 0;
        } else {
            $item['type'] = "item";
            $item['item_id'] = $userDrop->drop[0]->rust_id;
        }
        $result['data'] = $item;
        return $result;
    }

    /**
     *
     * @return array
     */
    private function methodBasket($steam_id) {
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
                          ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
                          ->all();

        $result = [];
        $data = [];
        foreach ($userDrops as $userDrop) {
            $item = [
                'id' => $userDrop->id,
                'amount' => $userDrop->count,
                'name' => $userDrop->drop[0]->name,
                'img' => Yii::$app->params['baseUrl'] . $userDrop->drop[0]->imageOrig->getImagePubUrl(),
                'blocked' => false,
                'block_date' => null,
            ];
            if (!empty($userDrop->drop[0]->blocked_at) && strtotime($userDrop->drop[0]->blocked_at) > time()) {
                $item['blocked'] = true;
                $item['block_date'] = strtotime($userDrop->drop[0]->blocked_at);
            }
            if (!empty($userDrop->drop[0]->command)) {
                $item['command'] = str_replace("\r", '', $userDrop->drop[0]->command);
                $item['type'] = "command";
                $item['item_id'] = 0;
            } else {
                $item['type'] = "item";
                $item['item_id'] = $userDrop->drop[0]->rust_id;
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


    public function actionWipeInfo($serverTag)
    {
        header('Content-type: application/json');
        /** @var Servers $server */
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = "Данных нет";
            $result['result'] = "fail";
            $result['code'] = 104;
            return json_encode($result,JSON_PRETTY_PRINT);
        }
        $result['lastWipe'] = (new \DateTime($server->wipe))->format('d.m.Y H:i');
        $result['nextWipe'] = (new \DateTime($server->next_wipe))->format('d.m.Y H:i');
        $result['code'] = 200;
        return json_encode($result,JSON_PRETTY_PRINT);
    }
}
