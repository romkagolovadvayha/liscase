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

class ApiRustRuController extends WebController
{
    public $enableCsrfValidation = false;
    CONST secretKey = '79f57ce93708fdbd05b57f6e48154724';

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

    /**
     * @param $secret
     * /api-rust-ru/get-users-wait-confirm?secret=79f57ce93708fdbd05b57f6e48154724
     *
     * @return false|string|string[]
     */
    public function actionGetUsersWaitConfirm($secret) {
        header('Content-type: application/json');
        if (self::secretKey !== $secret) {
            return json_encode([
                'result' => 'fail',
                'message' => 'Ошибка авторизации',
            ],JSON_PRETTY_PRINT);;
        }

        /** @var User[] $users */
        $users = User::find()
            ->andWhere(['rustru_activated' => 1])
            ->andWhere(['>', 'rustru_scrap_wait', 0])
            ->andWhere(['status' => User::STATUS_ACTIVE])
            ->all();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
              'steam_id' => $user->steam_id,
              'scrap' => number_format($user->rustru_scrap_wait, 0),
            ];
        }

        return json_encode([
                               'result' => 'success',
                               'data' => $data
                           ],JSON_PRETTY_PRINT);
    }

    /**
     * @param $secret
     * /api-rust-ru/scrap-confirm?secret=79f57ce93708fdbd05b57f6e48154724
     *
     */
    public function actionScrapConfirm($secret) {
        if (empty(Yii::$app->request->getRawBody())) {
            return json_encode([
                'result' => 'fail',
                'message' => 'Доступен только POST запрос',
            ],JSON_PRETTY_PRINT);
        }
        header('Content-type: application/json');
        $users = json_decode(Yii::$app->request->getRawBody(), 1);
        foreach ($users as $user) {
            $model = User::findBySteamId($user['steam_id']);
            $model->rustru_scrap_confirm += $user['scrap'];
            if ($model->rustru_scrap_wait < $user['scrap']) {
                continue;
            }
            $model->rustru_scrap_wait = 0;
            $model->save(false);
        }

        return json_encode([
                               'result' => 'success',
                           ],JSON_PRETTY_PRINT);
    }


    /**
     * @param $secret
     * /api-rust-ru/get-users?secret=79f57ce93708fdbd05b57f6e48154724
     *
     * @return false|string|string[]
     */
    public function actionGetUsers($secret) {
        header('Content-type: application/json');
        if (self::secretKey !== $secret) {
            return json_encode([
                'result' => 'fail',
                'message' => 'Ошибка авторизации',
            ],JSON_PRETTY_PRINT);
        }

        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['rustru_activated' => 1])
                     ->andWhere(['>', 'rustru_scrap_confirm', 0])
                     ->andWhere(['status' => User::STATUS_ACTIVE])
                     ->all();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'steam_id' => $user->steam_id,
                'scrap_confirmed' => number_format($user->rustru_scrap_confirm, 0),
            ];
        }

        return json_encode([
                               'result' => 'success',
                               'data' => $data
                           ],JSON_PRETTY_PRINT);
    }
}
