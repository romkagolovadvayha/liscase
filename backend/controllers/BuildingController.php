<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\models\building\Building;
use Yii;
use backend\models\building\BuildingSearch;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * BuildingController implements the CRUD actions for Building model.
 */
class BuildingController extends BackendController
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => AccessControl::class,
                    'rules' => [
                        [
                            'allow' => true,
                            'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
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
     * Lists all Building models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new BuildingSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить постройку'),
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
     * Displays a single Building model.
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
     * Creates a new Building model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    protected function clearBuildingsCache()
    {
        // Очищаем кэш списка построек (все возможные варианты limit и сортировки)
        for ($limit = 10; $limit <= 50; $limit += 10) {
            Yii::$app->cache->delete('api_buildings_list_created_at_desc_' . $limit);
            Yii::$app->cache->delete('api_buildings_list_created_at_asc_' . $limit);
            Yii::$app->cache->delete('api_buildings_list_likes_desc_' . $limit);
            Yii::$app->cache->delete('api_buildings_list_likes_asc_' . $limit);
            Yii::$app->cache->delete('api_buildings_list_name_desc_' . $limit);
            Yii::$app->cache->delete('api_buildings_list_name_asc_' . $limit);
        }
        // Очищаем все возможные комбинации кэша построек
        $sorts = ['created_at', 'likes', 'name'];
        $orders = ['asc', 'desc'];
        foreach ($sorts as $sort) {
            foreach ($orders as $order) {
                Yii::$app->cache->delete('api_buildings_list_' . $sort . '_' . $order);
            }
        }
    }

    public function actionCreate()
    {
        $model = new Building();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                // Очищаем кэш построек
                $this->clearBuildingsCache();
                Yii::$app->cache->delete('api_buildings_view_' . $model->id);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
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
     * Updates an existing Building model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            $this->clearBuildingsCache();
            Yii::$app->cache->delete('api_buildings_view_' . $model->id);
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionSuccess($id)
    {
        $model = $this->findModel($id);

        $model->status = Building::STATUS_ACTIVE;
        if ($model->save()) {
            $this->clearBuildingsCache();
            Yii::$app->cache->delete('api_buildings_view_' . $model->id);
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '🏠 Ваша постройка успешно прошла модерацию!');
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    public function actionReject($id)
    {
        $model = $this->findModel($id);

        $model->status = Building::STATUS_REJECT;
        if ($model->save()) {
            $this->clearBuildingsCache();
            Yii::$app->cache->delete('api_buildings_view_' . $model->id);
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '🏠 Ваша постройка не прошла модерацию!');
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Building model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        
        // Очищаем кэш перед удалением
        $this->clearBuildingsCache();
        Yii::$app->cache->delete('api_buildings_view_' . $id);

        foreach ($model->buildingResident as $resident) {
            $resident->delete();
        }
        $s3Api = Yii::$app->s3Api;
        foreach ($model->buildingImage as $image) {
            // Удаляем из S3
            $s3KeyOriginal = 'uploads/buildings/' . $image->image;
            $s3KeyPreview = 'uploads/buildings/preview_' . $image->image;
            $s3Api->deleteFile($s3KeyOriginal);
            $s3Api->deleteFile($s3KeyPreview);
            $image->delete();
        }
        foreach ($model->buildingLikes as $like) {
            $like->delete();
        }

        $model->delete();

        $this->clearBuildingsCache();
        Yii::$app->session->addFlash('success', Yii::t('common', 'Запись успешно удалена!'));
        return $this->redirect(['index']);
    }

    /**
     * Finds the Building model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Building the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Building::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
