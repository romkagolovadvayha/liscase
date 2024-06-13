<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\skindrops\Skindrops;
use common\models\user\User;
use frontend\forms\profile\ProfileForm;
use yii\base\BaseObject;
use yii\web\NotFoundHttpException;
use Yii;

class ApiSkindropsController extends WebController
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

    public function beforeAction($action)
    {
        return true;
    }

    public function actionGodraw() {
        header('Content-type: application/json');
        if ($this->getBearerToken() !== Yii::$app->params['skinDrops']['apiKey']) {
           return json_encode([
                            'code' => 'fail',
                            'errorRu' => 'Вы не авторизованы',
                            'errorEn' => 'Not auth',
                        ]);
        }
        $this->layout = 'service';
        $minOnline = 0;

        $params = json_decode(Yii::$app->request->getRawBody(), 1);

        if (empty($params)) {
            return json_encode([
                                   'code' => 'fail',
                                   'errorRu' => 'Не переданы параметры запроса',
                                   'errorEn' => 'Not request params',
            ]);
        }

        if ($params['onlineCount'] < $minOnline) {
            return $this->goDrawError([
                'errorRu' => $params['serverCode'] . ": " . 'Не хватает минимального количества игроков для старта розыгрыша',
                'errorEn' => $params['serverCode'] . ": " . 'Not enough minimum number of players to start the draw',
            ]);
        }

        $steamIds = explode(',', str_replace(' ', '', $params['steamIds']));
        $usersDroped = Skindrops::find()
                                ->select('DISTINCT(steam_id)')
                                ->andWhere(['IN', 'steam_id', $steamIds])
                                ->andWhere(['>', 'created_at', date('Y-m-d 00:00:01')])
                                ->createCommand()
                                ->queryColumn();

        $members = array_diff($steamIds, $usersDroped);
        $members = User::find()
                       ->alias('u')
                       ->joinWith(['userProfile up'])
                       ->select('DISTINCT(steam_id)')
                       ->andWhere(['IN', 'steam_id', $members])
                       ->andWhere(['up.skindrops' => 1])
                       ->createCommand()
                       ->queryColumn();
        if (empty($members)) {
            $members = $usersDroped;
            $members = User::find()
                           ->alias('u')
                           ->joinWith(['userProfile up'])
                           ->select('DISTINCT(steam_id)')
                           ->andWhere(['IN', 'steam_id', $members])
                           ->andWhere(['up.skindrops' => 1])
                           ->createCommand()
                           ->queryColumn();
        }

        shuffle($members);

        if (empty($members)) {
            return $this->goDrawError([
                                          'errorRu' => $params['serverCode'] . ": " . 'Не найдено ни одного активного участника',
                                          'errorEn' => $params['serverCode'] . ": " . 'No active members found',
                                      ]);
        }

        $winner = $members[0];

        /** @var User $user */
        $user = User::findBySteamId($winner);

        $partner = $this->getUrlQuery($user->userProfile->trade_link, 'partner');
        $token = $this->getUrlQuery($user->userProfile->trade_link, 'token');

        if (empty($partner) || empty($token)) {
            return $this->goDrawError([
                                          'errorRu' => $params['serverCode'] . ": " . "Трейд ссылка \"{$user->username}\" указана неверно!",
                                          'errorEn' => $params['serverCode'] . ": " . "Trade link \"{$user->username}\" incorrect!",
                                      ]);
        }

        $minPrice = 20;
        $maxPrice = 65;
        $items = [];
        $data = Yii::$app->rustTm->prices()['items'];
        shuffle($data);
        foreach ($data as $item) {
            if ($item['price'] > $item['avg_price'] + 5) {
                continue;
            }
            if ($item['price'] > $maxPrice || $item['price'] < $minPrice) {
                continue;
            }
            $items[] = [
                "name" => $item['market_hash_name'],
                "price" => $item['price'] + 10,
                "image" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/100.png",
                "image300" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/300.png"
            ];
            if (count($items) > 40) {
                break;
            }
        }
        $item = $items[0];

        $response = Yii::$app->rustTm->buy($item['name'], $item['price'] * 100, $partner, $token);

        if (!empty($response['error'])
            && (strpos($response['error'], 'Неверная ссылка для обмена') !== false
                || strpos($response['error'], 'инвентарь') !== false
                || strpos($response['error'], 'приватност') !== false)) {
            $user->userProfile->skindrops = 0;
            $user->userProfile->skindrops_error = $response['error'];
            $user->userProfile->save(false);
            return $this->goDrawError(['errorRu' => $params['serverCode'] . ": " . $user->username . ": " . $response['error'], 'errorEn' => $params['serverCode'] . ": " . $user->username . ": " . $response['error']]);
        } elseif (!empty($response['error'])) {
            return $this->goDrawError(['errorRu' => $params['serverCode'] . ": " . $user->username . ": " . $response['error'], 'errorEn' => $params['serverCode'] . ": " . $user->username . ": " . $response['error']]);
        }

        $price = round(($response['price'] / 100) * 1.25, 2);
        $priceEn = round((($response['price'] / 100) * 1.25) / 85, 2);

        $chatAlertTextRu = "<color=#aaf16e>{0}</color> выиграл скин <color=#aaf16e>{1}</color> (<color=#aaf16e>{2} RUB</color>)\nХотите тоже получать скины?\nПодробности в Discord: <color=#feeda1>discord.gg/prostoj</color>";
        $chatAlertTextEn = "<color=#aaf16e>{0}</color> won a skin <color=#aaf16e>{1}</color> (<color=#aaf16e>{2} $</color>)\nDo you want to receive skins too?\nDetails in Discord: <color=#feeda1>discord.gg/prostoj</color>";
        $chatAlertPlayerTextRu = "Поздравляем!\nВы выиграли скин <color=#aaf16e>{0}</color> (<color=#aaf16e>{1} RUB</color>)\nУ вас есть 5 минут чтобы принять трейд";
        $chatAlertPlayerTextEn = "Congratulations!\nYou have won a skin <color=#aaf16e>{0}</color> (<color=#aaf16e>{1} $</color>)\nYou have 5 minutes to accept the trade";

        $chatAlertTextRu = str_replace('{0}', $user->username, $chatAlertTextRu);
        $chatAlertTextRu = str_replace('{1}', $item['name'], $chatAlertTextRu);
        $chatAlertTextRu = str_replace('{2}', $price, $chatAlertTextRu);

        $chatAlertTextEn = str_replace('{0}', $user->username, $chatAlertTextEn);
        $chatAlertTextEn = str_replace('{1}', $item['name'], $chatAlertTextEn);
        $chatAlertTextEn = str_replace('{2}', $priceEn, $chatAlertTextEn);

        $chatAlertPlayerTextRu = str_replace('{0}', $item['name'], $chatAlertPlayerTextRu);
        $chatAlertPlayerTextRu = str_replace('{1}',$price, $chatAlertPlayerTextRu);

        $chatAlertPlayerTextEn = str_replace('{0}', $item['name'], $chatAlertPlayerTextEn);
        $chatAlertPlayerTextEn = str_replace('{1}',$priceEn, $chatAlertPlayerTextEn);

        $result = [
            'code' => 'success',
            'winner' => $winner,
            'chat_alert' => true,
            'chat_alert_text_ru' => $chatAlertTextRu,
            'chat_alert_text_en' => $chatAlertTextEn,
            'chat_alert_player' => true,
            'chat_alert_player_text_ru' => $chatAlertPlayerTextRu,
            'chat_alert_player_text_en' => $chatAlertPlayerTextEn,
            'sound' => true,
            'sound_prefab' => 'assets/prefabs/misc/easter/painted eggs/effects/eggpickup.prefab',
        ];

        $model = new Skindrops();
        $model->name = $item['name'];
        $model->steam_id = $winner;
        $model->player = $user->username;
        $model->price = $price;
        $model->real_price = round($response['price'] / 100, 2);
        $model->image = $item['image'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);

        $title = 'Произошёл розыгрыш скина! На сервере ' . $params['serverCode'];
        $description = "Игрок **[{$model->player}](http://steamcommunity.com/profiles/{$model->steam_id})** выиграл скин **{$model->name}**\nЦена в Steam: **{$model->price} RUB**";
        Yii::$app->discord->send(Yii::$app->params['skinDrops']['discordWebhook'], $title, $description, $item['image300']);

        return json_encode($result);
    }


    public function goDrawError($params) {
        Yii::$app->discord->send(Yii::$app->params['skinDrops']['discordWebhookAdmin'], 'Произошла ошибка розыгрыша!', $params['errorRu']);
        $params['code'] = 'fail';
        return json_encode($params);
    }

    private function getUrlQuery($url, $key = null) {
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (is_null($key)) {
                return $query;
            } elseif (isset($query[$key])) {
                return $query[$key];
            }
        }

        return false;
    }

    /**
     * Get header Authorization
     * */
    private function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * get access token from header
     * */
    private function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
