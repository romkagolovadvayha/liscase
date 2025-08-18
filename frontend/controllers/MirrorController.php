<?php

namespace frontend\controllers;

use common\models\mirrors\Mirrors;
use common\models\user\User;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class MirrorController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['webhook']
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    public function actionWebhook()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $data = json_decode(Yii::$app->request->rawBody, 1);
        //$data = json_decode('{"username":"Rust Connections","embeds":[{"title":"Новое подключение на МОСКВА [NOLIMIT|SKINS] Wi","color":8388863,"fields":[{"name":"SteamID","value":"76561199261574995","inline":true},{"name":"IP","value":"2.73.239.192:25254","inline":true},{"name":"Уникальных подключений","value":"5","inline":true}],"footer":{"text":"При перезапуске подключения обнуляются"}}]}', 1);

        $title = str_replace('Новое подключение на ', '', $data['embeds'][0]['title']);
        $steamId = $data['embeds'][0]['fields'][0]['value'];

        /** @var User $user */
        $user = User::find()
                    ->andWhere(['steam_id' => $steamId])
                    ->one();

        $model = new Mirrors();
        $model->steam_id = $steamId;
        $model->mirror_name = $title;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);

        $text = "📡 Новое подключение по зеркалу";
        $text .= PHP_EOL . "Название зеркала: {$title}";
        $text .= PHP_EOL . "SteamId: {$steamId}";

        if (empty($user)) {
            $user = User::findBySteamId($steamId, false, 'mirror 2');
            $user->is_mirror_registration = 1;
            $user->save();
            $text .= PHP_EOL . "Игрок: {$user->username}";
            $text .= PHP_EOL . "<b>Первый раз на сервере</b>";
        } else {
            $text .= PHP_EOL . "Игрок: {$user->username}";
            if ($user->is_mirror_registration) {
                $text .= PHP_EOL . "<b>Уже подключался к серверу с зеркала</b>";
                Yii::$app->telegramChats->sendMessage($text);
                return [
                    'success' => false
                ];
            }
            $text .= PHP_EOL . "<b>Вернулся старый игрок</b>";
            $user->is_mirror_returned = 1;
            $user->save();
        }
        Yii::$app->telegramChats->sendMessage($text);

        return [
            'success' => false
        ];
    }
}