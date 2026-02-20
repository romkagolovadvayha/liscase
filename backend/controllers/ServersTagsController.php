<?php

namespace backend\controllers;

use backend\components\CrudController;
use backend\models\ServersTagsSearch;
use common\components\helpers\Role;
use common\models\servers\ServersTags;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * ServersTagsController implements the CRUD actions for ServersTags model.
 */
class ServersTagsController extends CrudController
{
    public $enableCsrfValidation = false; // Временно для отладки
    
    /**
     * @return string
     */
    protected function _getSearchClassName()
    {
        return ServersTagsSearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return ServersTags::class;
    }

    /**
     * @return string
     */
    protected function _getFormLayout()
    {
        return '@backend/views/layouts/main';
    }

    /**
     * @return array
     */
    protected function getIndexHeaderActions()
    {
        return [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Создать тег'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
    }

    /**
     * @param int|null $id
     * @return \common\components\base\Model|ServersTags
     * @throws NotFoundHttpException
     */
    protected function _getFormModel($id = null)
    {
        $formModel = parent::_getFormModel($id);
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
        return $formModel;
    }

    public function actionIndex()
    {
        $this->_setSearchModel();
        $this->_rememberIndexUrl();
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $this->_searchModel;
        $this->view->params['headerActions'] = $this->getIndexHeaderActions();
        return $this->_renderIndex($this->_getSearchDataProvider());
    }

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => \yii\filters\AccessControl::class,
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
                    ],
                ],
            ]
        );
    }

    /**
     * Creates a new ServersTags model.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new ServersTags();
        
        // Проверка CSRF вручную для отладки
        if (Yii::$app->request->isPost) {
            $csrfToken = Yii::$app->request->post(Yii::$app->request->csrfParam);
            $validCsrf = Yii::$app->request->validateCsrfToken($csrfToken);
            
            Yii::info('POST Request to create tag', __METHOD__);
            Yii::info('CSRF Token valid: ' . ($validCsrf ? 'YES' : 'NO'), __METHOD__);
            
            if (!$validCsrf) {
                Yii::error('CSRF validation failed!', __METHOD__);
                Yii::$app->session->setFlash('error', 'CSRF токен недействителен. Попробуйте обновить страницу.');
                return $this->render('create', ['model' => $model]);
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            Yii::info('Model loaded successfully', __METHOD__);
            Yii::info('Icon value after load: ' . ($model->icon ?? 'NULL'), __METHOD__);
            Yii::info('POST data: ' . print_r(Yii::$app->request->post(), true), __METHOD__);
            
            if ($model->save()) {
                // Кэш сбрасывается в модели через afterSave
                Yii::$app->session->setFlash('success', 'Тег успешно создан');
                return $this->redirect(['view', 'id' => $model->id]);
            } else {
                Yii::error('Model save failed: ' . print_r($model->getErrors(), true), __METHOD__);
                Yii::$app->session->setFlash('error', 'Ошибка сохранения: ' . print_r($model->getErrors(), true));
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Displays a single ServersTags model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Deletes an existing ServersTags model.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $linkName = $model->link_name; // Сохраняем link_name перед удалением
        $model->delete(); // Кэш сбрасывается в модели через afterDelete
        
        Yii::$app->session->setFlash('success', 'Тег успешно удален');
        return $this->redirect(['index']);
    }

    /**
     * Finds the ServersTags model based on its primary key value.
     * @param int $id ID
     * @return ServersTags the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ServersTags::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}

