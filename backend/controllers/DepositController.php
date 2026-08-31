<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\invoice\Deposit;
use backend\models\DepositsSearch;
use yii\filters\AccessControl;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * DepositController implements the CRUD actions for Deposit model.
 */
class DepositController extends Controller
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
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'accept' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Lists all Deposit models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new DepositsSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . \Yii::t('common', 'Добавить депозит'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Deposit model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $actions = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . \Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
        if ($model->status != Deposit::STATUS_SUCCESS) {
            $actions[] = [
                'label' => '<i class="fas fa-edit"></i> ' . \Yii::t('common', 'Редактировать'),
                'url' => ['update', 'id' => $model->id],
                'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ];
            $actions[] = [
                'label' => '<i class="fas fa-check"></i> ' . \Yii::t('common', 'Принять'),
                'url' => ['accept', 'id' => $model->id],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
                'data' => ['confirm' => \Yii::t('common', 'Вы уверены, что хотите принять этот депозит?'), 'method' => 'post'],
            ];
            $actions[] = [
                'label' => '<i class="fas fa-trash"></i> ' . \Yii::t('common', 'Удалить'),
                'url' => ['delete', 'id' => $model->id],
                'class' => 'bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
                'data' => ['confirm' => \Yii::t('common', 'Вы уверены, что хотите удалить этот депозит?'), 'method' => 'post'],
            ];
        }
        $this->view->params['headerActions'] = $actions;
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new Deposit model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Deposit();

        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                $requestedStatus = (int)$model->status;
                if ($requestedStatus === Deposit::STATUS_SUCCESS) {
                    $model->status = Deposit::STATUS_WAIT_CONFIRM;
                }

                if ($model->save()) {
                    if ($requestedStatus === Deposit::STATUS_SUCCESS) {
                        $model->markSuccessful(true);
                    }
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        } else {
            $model->loadDefaultValues();
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . \Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Deposit model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $oldStatus = $model->status;

        if ($this->request->isPost && $model->load($this->request->post())) {
            $requestedStatus = (int)$model->status;

            if ((int)$oldStatus === Deposit::STATUS_SUCCESS) {
                if ($requestedStatus !== Deposit::STATUS_SUCCESS
                    || $model->isAttributeChanged('amount')
                    || $model->isAttributeChanged('payment_type')
                    || $model->isAttributeChanged('user_id')) {
                    $model->addError('status', 'Проведенный депозит нельзя изменять или отменять.');
                } elseif ($model->save()) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            } elseif ($requestedStatus === Deposit::STATUS_SUCCESS) {
                // Save editable metadata first, keeping the old financial
                // status. Completion itself must go through the atomic path.
                $model->status = $oldStatus;
                if ($model->save() && $model->markSuccessful(true)) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
                $model->addError('status', 'Депозит уже обработан другим запросом.');
            } elseif ($model->save()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . \Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Accepts a deposit (sets status to SUCCESS)
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionAccept($id)
    {
        $model = $this->findModel($id);
        if ($model->markSuccessful(true)) {
            \Yii::$app->session->setFlash('success', 'Депозит успешно принят!');
        } else {
            \Yii::$app->session->setFlash('warning', 'Депозит уже принят.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Deletes an existing Deposit model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ((int)$model->status === Deposit::STATUS_SUCCESS) {
            throw new BadRequestHttpException('Проведенный депозит нельзя удалить.');
        }
        $model->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Deposit model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Deposit the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Deposit::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
