<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\skindrops\Skindrops;
use frontend\forms\profile\ProfileForm;
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
        $this->layout = 'service';
        $minOnline = 20;

        $params = json_decode(Yii::$app->request->getRawBody(), 1);
        $steamIds = $params['steamIds'];

        if ($params['onlineCount'] < $minOnline) {
            return json_encode([
                'code' => 'fail',
                'errorRu' => 'Не хватает минимального количества игроков для старта розыгрыша',
                'errorEn' => 'Not enough minimum number of players to start the draw',
            ]);
        }

        $usersDroped = Skindrops::find()
            ->select('DISTINCT steam_id')
            ->andWhere(['IN', 'steam_id', $steamIds])
            ->andWhere(['>', 'created_at', date('Y-m-d 00:00:01')])
            ->createCommand()
            ->queryColumn();

        print_r($usersDroped);exit;

        $chatAlertTextRu = "{0} выиграл скин {1} ({2} RUB)";
        $chatAlertTextEn = "{0} won a skin {1} ({2} $)";
        $chatAlertPlayerTextRu = "Поздравляем!\nВы выиграли скин {0} ({1} RUB)\nУ вас есть 5 минут чтобы принять трейд";
        $chatAlertPlayerTextEn = "Congratulations!\nYou have won a skin {0} ({1}$ RUB)\nYou have 5 minutes to accept the trade";
        $winner = "";

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

        return json_encode($result);
    }

}
