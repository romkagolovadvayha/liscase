<?php

namespace backend\controllers;

use backend\forms\box\BoxForm;
use backend\forms\box\PromocodeForm;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\box\Box;
use common\models\box\BoxSearch;
use common\models\promocode\Promocode;
use common\models\promocode\PromocodeSearch;
use yii\base\BaseObject;
use yii\web\Response;
use Yii;

class PromocodeController extends \backend\components\CrudController
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

    protected function _getSearchClassName()
    {
        return PromocodeSearch::class;
    }

    protected function _getFormClassName()
    {
        return PromocodeForm::class;
    }

    protected function getIndexHeaderActions()
    {
        return [
            [
                'label' => '<i class="fas fa-plus"></i> Новый промокод',
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
    }

    /**
     * @param Model $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        if ($formModel->load(Yii::$app->request->post())) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return \yii\bootstrap5\ActiveForm::validate($formModel);
            }
            if ($formModel->saveRecord()) {
                return $this->redirect($this->getIndexUrl());
            }
        }
        return $this->render($view, [
            'model' => $formModel,
        ]);
    }

    public function actionCreate()
    {
        $model = new PromocodeForm();
        $model->type = 2;
        $date = new \DateTime();
        $date->modify('+1 day');
        $model->finished_at = $date->format('Y-m-d H:i:s');

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                return $this->redirect(['/promocode']);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->renderAjax('create', [
            'model' => $model,
        ]);
    }
}