<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use common\components\helpers\Role;

/**
 * Контроллер для демонстрации дизайн-системы
 */
class DesignSystemController extends Controller
{
    /**
     * {@inheritdoc}
     */
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

    /**
     * Демонстрация всех элементов дизайн-системы
     * @return string
     */
    public function actionIndex()
    {
        $this->view->title = 'Дизайн-система';
        return $this->render('index');
    }
}

