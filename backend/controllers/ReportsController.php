<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\invoice\Invoice;
use common\models\skindrops\Skindrops;
use yii\web\Controller;
use Yii;

class ReportsController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

}