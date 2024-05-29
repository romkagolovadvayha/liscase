<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\skindrops\SkindropsLink;
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

    public function actionBuy($name, $price, $partner)
    {
        /** @var SkindropsLink $link */
        $link = SkindropsLink::find()
            ->andWhere(['partner' => $partner])
            ->one();

        if (!empty($link) && !empty($link->token)) {
            $response = Yii::$app->rustTm->buy($name, $price, $partner, $link->token);
            if (!empty($response['error'])) {
                Yii::$app->session->addFlash('danger', $response['error']);
            } else {
                Yii::$app->session->addFlash('success', 'Скин успешно отправлен!');
            }
            $this->redirect('index');
        } else {
            throw new NotFoundHttpException('News not found');
        }
    }

}