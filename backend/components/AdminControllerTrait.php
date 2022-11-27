<?php

namespace backend\components;

use Yii;
use common\components\helpers\Role;

trait AdminControllerTrait
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_SUPPORT, Role::ROLE_SALES],
                    ],
                ],
            ],
        ];
    }
}