<?php

namespace common\components\web;

use Yii;
use yii\web\ForbiddenHttpException;

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
                    throw new ForbiddenHttpException(Yii::t('common', 'Авторизайтесь на сайте, чтобы получить доступ к этой странице'));
                }
            ],
        ];
    }
}