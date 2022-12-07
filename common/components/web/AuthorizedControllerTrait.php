<?php

namespace common\components\web;

use Yii;

trait AuthorizedControllerTrait
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    throw new \Exception(Yii::t('common', 'У вас нет доступа к этой странице'), 403);
                }
            ],
        ];
    }
}