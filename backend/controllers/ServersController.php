<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\servers\Servers;
use common\models\servers\ServersTagsRelation;
use backend\models\ServersSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * ServersController implements the CRUD actions for Servers model.
 */
class ServersController extends Controller
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
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Servers models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new ServersSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Creates a new Servers model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Servers();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                // Сохраняем теги
                $this->saveTags($model, Yii::$app->request->post('server_tags', []));
                
                Yii::$app->session->setFlash('success', 'Сервер успешно создан');
                return $this->redirect(['index']);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Servers model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            // Сохраняем теги
            $this->saveTags($model, Yii::$app->request->post('server_tags', []));
            
            Yii::$app->session->setFlash('success', 'Сервер успешно обновлен');
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Сохранение тегов сервера
     * @param Servers $model
     * @param array $tagIds
     */
    protected function saveTags($model, $tagIds = [])
    {
        // Удаляем старые связи
        ServersTagsRelation::deleteAll(['server_id' => $model->id]);
        
        // Добавляем новые связи
        if (!empty($tagIds) && is_array($tagIds)) {
            foreach ($tagIds as $tagId) {
                $relation = new ServersTagsRelation();
                $relation->server_id = $model->id;
                $relation->tag_id = $tagId;
                $relation->save();
            }
        }
    }

    /**
     * Finds the Servers model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id
     * @return Servers the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Servers::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionSort()
    {
        if (!empty($_POST)) {
            $sort = 0;
            foreach ($_POST['items'] as $itemId) {
                $model = Servers::findOne($itemId);
                $model->sort = $sort;
                $model->save();
                $sort++;
            }
        }

        /** @var Servers[] $models */
        $models = Servers::find()
                     ->andWhere(['status' => Servers::STATUS_ACTIVE])
                     ->orderBy(['sort' => SORT_ASC])
                     ->all();

        return $this->render('sort', [
            'items' => $models
        ]);
    }
}
