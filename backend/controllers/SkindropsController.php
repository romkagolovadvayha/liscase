<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\skindrops\Skindrops;
use common\models\skindrops\SkindropsLink;
use common\models\user\User;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii;

class SkindropsController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionReport()
    {
        return $this->render('report');
    }

    public function actionBuy($name, $price, $userId, $childId = null)
    {
        /** @var User $user */
        $user = User::findOne($userId);
        if (!empty($user)) {
            $partner = Skindrops::getUrlQuery($user->userProfile->trade_link, 'partner');
            $token = Skindrops::getUrlQuery($user->userProfile->trade_link, 'token');
            $response = Yii::$app->rustTm->buy($name, $price, $partner, $token);
            if (!empty($response['error'])) {
                Yii::$app->session->addFlash('danger', $response['error']);
            } else {
                if (!empty($childId)) {
                    /** @var User $childUser */
                    $childUser = User::findOne($childId);
                    $childUser->parent_skin_send = 1;
                    $childUser->save();
                }
                Yii::$app->session->addFlash('success', 'Скин успешно отправлен!');
            }
            $this->redirect('index');
        } else {
            throw new NotFoundHttpException('User not found');
        }
    }

}