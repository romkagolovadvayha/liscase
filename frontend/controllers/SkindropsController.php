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
        $model = null;
        $user = null;
        if (!Yii::$app->user->isGuest) {
            $user  = Yii::$app->user->identity;
            $model = ProfileForm::findOne($user->userProfile->id);

            if ($model->load(Yii::$app->request->post())) {
                if ($model->saveRecord()) {
                    Yii::$app->session->addFlash('success', 'Ссылка на трейд успешно привязана!');
                    return $this->refresh();
                }
            }
        }

        return $this->render('index', [
            'model' => $model,
            'user' => $user,
        ]);
    }

    public function actionGodraw() {
        header('Content-type: application/json');
        $this->layout = 'service';

        print_r(Yii::$app->request->post());
exit;
        return json_encode(Yii::$app->request->post());
    }

}
