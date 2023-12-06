<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\components\queue\openAi\GenPostJob;
use common\components\queue\openAi\GenPostsJob;
use common\models\blog\Blog;
use backend\models\blog\BlogSearch;
use common\models\blog\BlogCategory;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * BlogController implements the CRUD actions for Blog model.
 */
class BlogController extends Controller
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

    /**
     * Lists all Blog models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new BlogSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionGeneratePost($postId)
    {
        $cacheKey  = 'actionGenerate_Post_' . $postId;
        $cacheData = Yii::$app->cache->get($cacheKey);
        if (!empty($cacheData)) {
            Yii::$app->session->addFlash('success', 'Процесс генерации уже запущен, ожидайте.');
            return $this->redirect(['/blog/view', 'id' => $postId]);
        }
        Yii::$app->queueOpenAi->push(new GenPostJob([
            'postId' => $postId
        ]));
        Yii::$app->cache->set($cacheKey, $cacheKey, 6 * 60 * 60);
        return $this->redirect(['/blog/view', 'id' => $postId]);
    }

    public function actionGenerate($categoryId = null)
    {
        $categoryIds = [];
        if (!empty($categoryId)) {
            $categoryIds[] = $categoryId;
        } else {
            /** @var BlogCategory[] $categories */
            $categories = BlogCategory::find()->all();
            foreach ($categories as $item) {
                $categoryIds[] = $item->id;
            }
        }

        foreach ($categoryIds as $categoryId) {
            $cacheKey  = 'actionGenerate_Posts_' . $categoryId;
            $cacheData = Yii::$app->cache->get($cacheKey);
            if (!empty($cacheData)) {
                Yii::$app->session->addFlash('success', 'Процесс генерации уже запущен, ожидайте.');

                return $this->redirect(['/blog-category/index']);
            }
            Yii::$app->cache->delete('actionGenerateError_Posts_' . $categoryId);

            Yii::$app->queueOpenAi->push(new GenPostsJob([
                'categoryId' => $categoryId,
            ]));

            Yii::$app->session->addFlash('success', 'Процесс генерации запущен, ожидайте.');
            Yii::$app->cache->set($cacheKey, $cacheKey, 6 * 60 * 60);
            Yii::$app->cache->set('actionGeneratePosts', $cacheKey, 6 * 60 * 60);
        }
        return $this->redirect(['/blog-category/index']);
    }

    /**
     * Displays a single Blog model.
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
     * Creates a new Blog model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Blog();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
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
     * Updates an existing Blog model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Blog model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Blog model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Blog the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Blog::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
