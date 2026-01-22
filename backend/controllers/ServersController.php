<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\models\servers\Servers;
use common\models\servers\ServersTagsRelation;
use backend\models\ServersSearch;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * ServersController implements the CRUD actions for Servers model.
 */
class ServersController extends BackendController
{

    public function behaviors()
    {
        return [
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

    /**
     * Страница для массового редактирования дат вайпа серверов
     */
    public function actionMassEditWipe()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(30)
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        return $this->render('mass-edit-wipe', [
            'servers' => $servers,
        ]);
    }

    /**
     * AJAX endpoint для сохранения массовых изменений дат вайпа
     */
    public function actionSaveMassWipe()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $serverIds = Yii::$app->request->post('server_ids', []);
        // Если пришел не массив, преобразуем
        if (!is_array($serverIds)) {
            $serverIds = [$serverIds];
        }

        $wipe = Yii::$app->request->post('wipe', '');
        $nextWipe = Yii::$app->request->post('next_wipe', '');
        $globalWipe = Yii::$app->request->post('global_wipe', '');

        if (empty($serverIds)) {
            return [
                'success' => false,
                'message' => 'Не выбраны серверы для редактирования',
            ];
        }

        // Проверяем, что хотя бы одно поле заполнено
        if (empty($wipe) && empty($nextWipe) && empty($globalWipe)) {
            return [
                'success' => false,
                'message' => 'Необходимо заполнить хотя бы одно поле',
            ];
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['id' => $serverIds])
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->all();

        if (empty($servers)) {
            return [
                'success' => false,
                'message' => 'Серверы не найдены',
            ];
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($servers as $server) {
            try {
                $updated = false;

                // Обновляем только заполненные поля
                if (!empty($wipe)) {
                    // Валидация формата даты
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $wipe);
                    if ($date === false) {
                        throw new \Exception("Неверный формат даты для 'Последний вайп': {$wipe}. Ожидается формат: YYYY-MM-DD HH:MM:SS");
                    }
                    $server->wipe = $wipe;
                    $updated = true;
                }

                if (!empty($nextWipe)) {
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $nextWipe);
                    if ($date === false) {
                        throw new \Exception("Неверный формат даты для 'Следующий вайп': {$nextWipe}. Ожидается формат: YYYY-MM-DD HH:MM:SS");
                    }
                    $server->next_wipe = $nextWipe;
                    $updated = true;
                }

                if (!empty($globalWipe)) {
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $globalWipe);
                    if ($date === false) {
                        throw new \Exception("Неверный формат даты для 'Глобал вайп': {$globalWipe}. Ожидается формат: YYYY-MM-DD HH:MM:SS");
                    }
                    $server->global_wipe = $globalWipe;
                    $updated = true;
                }

                if ($updated && $server->save(false)) {
                    $results[$server->id] = [
                        'success' => true,
                        'message' => 'Сервер успешно обновлен',
                        'server_name' => $server->name,
                    ];
                    $successCount++;
                } else {
                    $errors = $server->getFirstErrors();
                    $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Ошибка сохранения';
                    $results[$server->id] = [
                        'success' => false,
                        'message' => $errorMessage,
                        'server_name' => $server->name,
                    ];
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $results[$server->id] = [
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage(),
                    'server_name' => $server->name,
                ];
                $errorCount++;
            }
        }

        return [
            'success' => $errorCount === 0,
            'message' => "Обновлено серверов: {$successCount}" . ($errorCount > 0 ? ", ошибок: {$errorCount}" : ''),
            'results' => $results,
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ];
    }
}
