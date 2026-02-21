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

    public function actionIndex()
    {
        $this->_setSearchModel();
        $this->_searchModel->tab = Yii::$app->request->get('tab');
        $this->_searchModel->load(Yii::$app->request->get());
        $this->_rememberIndexUrl();
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $this->_searchModel;
        $this->view->params['headerActions'] = $this->getIndexHeaderActions();
        return $this->_renderIndex($this->_getSearchDataProvider());
    }

    protected function getIndexHeaderActions()
    {
        return [
            [
                'label' => '<i class="fas fa-plus"></i> Новый промокод',
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
            [
                'label' => '<i class="fas fa-barcode"></i> Сгенерировать одноразовые',
                'url' => ['generate-batch'],
                'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
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

    /**
     * Генерация пачки одноразовых бессрочных промокодов.
     * GET: форма (количество, сумма), POST: создание и вывод списка кодов.
     */
    public function actionGenerateBatch()
    {
        $post = $this->request->post('DynamicModel', $this->request->post());
        $quantity = (int) ($post['quantity'] ?? 0);
        $amount = (int) ($post['amount'] ?? 0);

        if ($this->request->isPost && $quantity > 0 && $quantity <= 1000 && $amount >= 0) {
            $codes = [];
            for ($i = 0; $i < $quantity; $i++) {
                $code = Promocode::generateUniqueCode();
                $model = new Promocode();
                $model->code = $code;
                $model->type = Promocode::TYPE_PAYMENT_PAYMENT;
                $model->status = Promocode::STATUS_ACTIVE;
                $model->is_single_use = 1;
                $model->amount = $amount;
                $model->finished_at = null;
                $model->created_at = date('Y-m-d H:i:s');
                $model->save(false);
                $codes[] = $code;
            }
            return $this->render('generate-batch-result', [
                'codes' => $codes,
                'amount' => $amount,
            ]);
        }

        return $this->render('generate-batch', [
            'quantity' => $quantity ?: 100,
            'amount' => $amount ?: 50,
        ]);
    }
}