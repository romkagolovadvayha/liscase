<?php

namespace frontend\controllers;

use common\controllers\WebController;
use frontend\forms\profile\RustruForm;
use yii\web\NotFoundHttpException;
use Yii;

class RustruController extends WebController
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

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $model = null;
        $user = null;
        if (!Yii::$app->user->isGuest) {
            $user  = Yii::$app->user->identity;
            $model = RustruForm::findOne($user->id);
            if (!empty(Yii::$app->request->post())) {
                if ($model->saveRecord()) {
                    Yii::$app->session->addFlash('success', 'RustRu программа успешно активирована!');
                    return $this->refresh();
                } else {
                    Yii::$app->session->addFlash('danger', 'Ошибка активации программы!');
                    return $this->refresh();
                }
            }
        } else {
            if (!empty(Yii::$app->request->post())) {
                Yii::$app->session->addFlash('danger', 'Сначало вам нужно авторизоваться на сайте!');
            }
        }

        return $this->render('index', [
            'model' => $model,
            'user' => $user,
        ]);
    }

}
