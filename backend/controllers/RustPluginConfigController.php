<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\rustplugin\RustPluginConfig;
use common\models\rustplugin\RustPluginConfigSearch;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use backend\components\BackendController;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * RustPluginConfigController implements the CRUD actions for RustPluginConfig model.
 */
class RustPluginConfigController extends BackendController
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
                    'reload' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Lists all RustPluginConfig models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new RustPluginConfigSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single RustPluginConfig model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> Назад',
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
            [
                'label' => '<i class="fas fa-pencil-alt"></i> Изменить',
                'url' => ['update', 'id' => $model->id],
                'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
            [
                'label' => '<i class="fas fa-trash"></i> Удалить',
                'url' => ['delete', 'id' => $model->id],
                'class' => 'bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
                'data' => ['confirm' => 'Вы уверены, что хотите удалить этот конфиг?', 'method' => 'post'],
            ],
        ];
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new RustPluginConfig model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new RustPluginConfig();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                Yii::$app->session->setFlash('success', 'Сохранено');
                return $this->redirect(['update', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing RustPluginConfig model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Сохранено');
            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing RustPluginConfig model.
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
     * Reload плагина на указанном сервере
     * @param int $id ID конфига
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionReload($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $model = $this->findModel($id);
        $serverTag = Yii::$app->request->post('server_tag');
        
        if (empty($serverTag)) {
            return [
                'success' => false,
                'message' => 'Необходимо указать сервер',
            ];
        }
        
        // Проверяем существование сервера
        $server = Servers::findOne(['tag' => $serverTag]);
        if (!$server) {
            return [
                'success' => false,
                'message' => 'Сервер не найден',
            ];
        }
        
        // Формируем команду reload плагина
        $command = 'oxide.reload ' . $model->name;
        
        // Выполняем команду на указанном сервере
        $results = RconTasks::executeWithResults($command, [$serverTag]);
        
        if (isset($results[$serverTag])) {
            $result = $results[$serverTag];
            return [
                'success' => empty($result['error']),
                'message' => empty($result['error']) ? 'Плагин успешно перезагружен' : 'Ошибка: ' . $result['error'],
                'result' => $result['result'],
                'error' => $result['error'],
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Не удалось выполнить команду',
        ];
    }

    /**
     * Finds the RustPluginConfig model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return RustPluginConfig the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = RustPluginConfig::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}

