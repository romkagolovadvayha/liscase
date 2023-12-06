<?php

namespace backend\controllers;

use backend\forms\settings\SettingsForm;
use backend\forms\settings\ThemeForm;
use common\components\helpers\Role;
use common\models\settings\Settings;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

class SettingsController extends Controller
{

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_SUPPORT],
                    ],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        $model = new SettingsForm();

        if ($model->load(Yii::$app->request->post()) && $model->saveRecord()) {
            Yii::$app->session->setFlash('success', 'Настройки успешно сохранены!');
        }

        return $this->render('index', [
            'model' => $model,
        ]);
    }

    public function actionTheme()
    {
        $model = new ThemeForm();

        if ($model->load(Yii::$app->request->post()) && $model->saveRecord()) {
            Yii::$app->session->setFlash('success', 'Настройки успешно сохранены!');
        }

        return $this->render('theme', [
            'model' => $model,
        ]);
    }

}
