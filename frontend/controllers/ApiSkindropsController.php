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

        $params = json_decode(Yii::$app->request->getRawBody(), 1);
        print_r($params);
exit;
        return json_encode(Yii::$app->request->getRawBody());
    }

}
