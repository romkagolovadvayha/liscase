<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\serverskin\ServerSkin;
use common\models\tasks\Task;
use common\models\user\UserTask;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

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
                        'success' => ['POST'],
                        'reject' => ['POST'],
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

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить скин'),
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
                // Очищаем кэш API скинов
                $this->clearCustomSkinsCache();
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
            // Очищаем кэш API скинов
            $this->clearCustomSkinsCache();
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

        $model->status = ServerSkin::STATUS_ACTIVE;
        if ($model->save()) {
            // Очищаем кэш API скинов
            $this->clearCustomSkinsCache();
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '👕 Ваш скин успешно прошел модерацию и добавлен на сервера!');
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

        $push = true;
        if ($model->status == ServerSkin::STATUS_ACTIVE) {
            $push = false;
        }

        $model->status = ServerSkin::STATUS_REJECT;
        if ($model->save()) {
            // Очищаем кэш API скинов
            $this->clearCustomSkinsCache();
            if (!empty($model->user->telegram_chat_id) && $push) {
                Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '👕 Ваш скин не прошел модерацию!');
            }
            RconTasks::execute("skinbox.removeskin {$model->skin_id}");
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
        
        // Очищаем кэш API скинов перед удалением
        $this->clearCustomSkinsCache();
        
        // Удаляем изображения из S3
        $s3Api = Yii::$app->s3Api;
        if (!empty($model->image)) {
            $s3Key = ltrim($model->image, '/');
            if (strpos($s3Key, 'uploads/') !== 0) {
                $s3Key = 'uploads' . $model->image;
            }
            $s3Api->deleteFile($s3Key);
        }
        if (!empty($model->image_64)) {
            $s3Key = 'uploads' . $model->image_64;
            $s3Api->deleteFile($s3Key);
        }
        if (!empty($model->image_150)) {
            $s3Key = 'uploads' . $model->image_150;
            $s3Api->deleteFile($s3Key);
        }

        $model->delete();

        Yii::$app->session->addFlash('success', Yii::t('common', 'Запись успешно удалена!'));
        return $this->redirect(['index']);
    }

    /**
     * Очистка кэша API скинов
     */
    protected function clearCustomSkinsCache()
    {
        // Очищаем кэш списка скинов (все возможные варианты limit)
        for ($limit = 10; $limit <= 50; $limit += 10) {
            Yii::$app->cache->delete('api_custom_skins_list_' . $limit);
        }
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
