<?php

namespace frontend\controllers;

use common\controllers\WebController;
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

        print_r(Yii::$app->request->post());
exit;
        return json_encode(Yii::$app->request->post());
    }

}
