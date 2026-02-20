<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\components\queue\openAi\GenCategoriesJob;
use common\models\blog\BlogCategory;
use backend\models\blog\BlogCategorySearch;
use backend\components\BackendController;
use yii\base\BaseObject;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * BlogCategoryController implements the CRUD actions for BlogCategory model.
 */
class BlogCategoryController extends BackendController
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
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Lists all BlogCategory models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $cacheData = Yii::$app->cache->get('actionGenerateError');
        if (!empty($cacheData)) {
            Yii::$app->session->addFlash('danger', "Ошибка генерации: " . $cacheData);
        }
        $searchModel = new BlogCategorySearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить категорию'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
            [
                'label' => '<i class="fas fa-newspaper"></i> ' . Yii::t('common', 'Блог'),
                'url' => ['/blog/index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single BlogCategory model.
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

    public function actionGenerate()
    {
        $cacheKey = 'actionGenerate';
        $cacheData = Yii::$app->cache->get($cacheKey);
        if (!empty($cacheData)) {
            Yii::$app->session->addFlash('success', 'Процесс генерации уже запущен, ожидайте.');
            return $this->redirect(['index']);
        }
        Yii::$app->cache->delete('actionGenerateError');

        Yii::$app->queueOpenAi->push(new GenCategoriesJob());
        Yii::$app->session->addFlash('success', 'Процесс генерации запущен, ожидайте.');
        Yii::$app->cache->set($cacheKey, $cacheKey, 6 * 60 * 60);
        return $this->redirect(['index']);
    }

    /**
     * Creates a new BlogCategory model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new BlogCategory();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                // Сбрасываем кэш категорий блога
                Yii::$app->cache->delete('api_blog_categories');
                
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing BlogCategory model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            // Сбрасываем кэш категорий блога
            Yii::$app->cache->delete('api_blog_categories');
            
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing BlogCategory model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        // Сбрасываем кэш категорий блога
        Yii::$app->cache->delete('api_blog_categories');

        return $this->redirect(['index']);
    }

    /**
     * Finds the BlogCategory model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return BlogCategory the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BlogCategory::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
