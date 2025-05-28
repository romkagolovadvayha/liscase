<?php

namespace backend\controllers;

use common\models\rcon\RconTasks;
use common\models\serverskin\ServerSkin;
use common\models\tasks\Task;
use common\models\user\UserTask;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ServerSkinController implements the CRUD actions for ServerSkin model.
 */
class ServerSkinController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
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
     * Lists all ServerSkin models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new \backend\models\serverskin\ServerSkinSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ServerSkin model.
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
     * Creates a new ServerSkin model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new ServerSkin();

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
     * Updates an existing ServerSkin model.
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

    public function actionSuccess($id)
    {
        $model = $this->findModel($id);

        $model->status = ServerSkin::STATUS_ACTIVE;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                //Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '👕 Ваш скин успешно прошел модерацию и добавлен на сервера!');
            }
            RconTasks::execute("skinbox.addskin {$model->skin_id}");
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    public function actionReject($id)
    {
        $model = $this->findModel($id);

        $model->status = ServerSkin::STATUS_REJECT;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '👕 Ваш скин не прошел модерацию!');
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing ServerSkin model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        $model->delete();

        Yii::$app->session->addFlash('success', Yii::t('common', 'Запись успешно удалена!'));
        return $this->redirect(['index']);
    }

    /**
     * Finds the ServerSkin model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return ServerSkin the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ServerSkin::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
