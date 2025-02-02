<?php

namespace frontend\controllers;

use common\controllers\WebController;
use frontend\forms\profile\ProfileForm;
use yii\web\NotFoundHttpException;
use Yii;

class SkindropsController extends WebController
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
        if (!Yii::$app->params['skindrops']) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $model = null;
        $user = null;
        if (!Yii::$app->user->isGuest) {
            $user  = Yii::$app->user->identity;
            $model = ProfileForm::findOne($user->userProfile->id);

            if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
                if ($model->saveRecord()) {
                    Yii::$app->session->addFlash('success', 'Ссылка на трейд успешно привязана!');
                    return $this->refresh();
                } else {
                    if (!empty($model->getFirstError('global'))) {
                        Yii::$app->session->addFlash('danger', $model->getFirstError('global'));
                    }
                }
            }
        }

        return $this->render('index', [
            'model' => $model,
            'user' => $user,
        ]);
    }

}
