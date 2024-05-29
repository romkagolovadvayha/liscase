<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\box\Drop;
use yii\web\Controller;
use Yii;

class WipeController extends Controller
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

    public function actionBlock()
    {
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->all();

        foreach ($drops as $drop) {
            if (!empty($drop->blocked_hour)) {
                $date = new \DateTime();
                $date->modify("+{$drop->blocked_hour} hour");
                $drop->blocked_at = $date->format('Y-m-d H:i:s');
                $drop->save();
            }
        }
        Yii::$app->session->addFlash('success', 'Предметы успешно заблокированы!');
        return $this->redirect('index');
    }

}