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
            return [
                'result' => 'fail',
                'message' => 'Ошибка авторизации',
            ];
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
        header('Content-type: application/json');
        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['rustru_activated' => 1])
                     ->andWhere(['>', 'rustru_scrap_wait', 0])
                     ->andWhere(['status' => User::STATUS_ACTIVE])
                     ->all();

        foreach ($users as $user) {
            $user->rustru_scrap_confirm += $user->rustru_scrap_wait;
            $user->rustru_scrap_wait = 0;
            $user->save(false);
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
            return [
                'result' => 'fail',
                'message' => 'Ошибка авторизации',
            ];
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
