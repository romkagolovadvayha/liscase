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
            if ($model->load($this->request->post())) {
                // Логируем данные для отладки
                Yii::info('Попытка создания сервера. Данные: ' . json_encode($model->attributes), __METHOD__);
                
                // Проверяем валидацию перед сохранением
                if (!$model->validate()) {
                    // Ошибки валидации уже добавлены к модели, форма их покажет
                    $errors = $model->getErrors();
                    Yii::warning('Ошибки валидации при создании сервера: ' . json_encode($errors), __METHOD__);
                } else {
                    try {
                        Yii::info('Валидация прошла успешно, пытаемся сохранить модель', __METHOD__);
                        if ($model->save()) {
                            // Сохраняем теги
                            $this->saveTags($model, Yii::$app->request->post('server_tags', []));
                            
                            // Сбрасываем кэш списка серверов и детальной информации
                            $this->clearServersCache($model->tag);
                            
                            // Сбрасываем кэш карт, если указан map_list_id
                            if (!empty($model->map_list_id)) {
                                $this->clearMapsCache();
                            }
                            
                            Yii::$app->session->setFlash('success', 'Сервер успешно создан');
                            return $this->redirect(['index']);
                        }
                    } catch (\yii\db\Exception $e) {
                        // Обработка ошибок базы данных, включая нарушение check constraint
                        $errorMessage = 'Ошибка сохранения в базе данных. ';
                        
                        if (strpos($e->getMessage(), 'Check constraint') !== false || strpos($e->getMessage(), 'servers_chk_1') !== false) {
                            $errorMessage .= 'Нарушение ограничений базы данных. Проверьте корректность введенных данных:';
                            $errorMessage .= '<ul>';
                            $errorMessage .= '<li>Даты вайпов должны быть в правильном порядке (последний вайп ≤ следующий вайп ≤ глобальный вайп)</li>';
                            $errorMessage .= '<li>Минимальный размер карты не должен быть больше максимального</li>';
                            $errorMessage .= '<li>Проверьте другие ограничения базы данных</li>';
                            $errorMessage .= '</ul>';
                            
                            // Добавляем ошибку к модели, чтобы она отобразилась в форме
                            $model->addError('name', $errorMessage);
                            
                            Yii::error('Check constraint violation: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
                        } else {
                            $errorMessage .= $e->getMessage();
                            $model->addError('name', $errorMessage);
                            Yii::error('Database error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
                        }
                        
                        // Также показываем flash сообщение
                        Yii::$app->session->setFlash('error', $errorMessage);
                    } catch (\Exception $e) {
                        $errorMessage = 'Ошибка сохранения: ' . $e->getMessage();
                        $model->addError('name', $errorMessage);
                        Yii::$app->session->setFlash('error', $errorMessage);
                        Yii::error('Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
                    }
                }
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

        if ($this->request->isPost && $model->load($this->request->post())) {
            // Проверяем валидацию перед сохранением
            if (!$model->validate()) {
                // Ошибки валидации уже добавлены к модели, форма их покажет
            } else {
                try {
                    if ($model->save()) {
                        // Сохраняем теги
                        $this->saveTags($model, Yii::$app->request->post('server_tags', []));
                        
                        // Сбрасываем кэш списка серверов и детальной информации
                        $this->clearServersCache($model->tag);
                        
                        // Сбрасываем кэш календаря вайпов (все возможные комбинации)
                        $this->clearWipeCalendarCache();
                        
                        // Сбрасываем кэш карт, если изменился map_list_id
                        if (isset($model->oldAttributes['map_list_id']) && $model->oldAttributes['map_list_id'] != $model->map_list_id) {
                            $this->clearMapsCache();
                        }
                        
                        Yii::$app->session->setFlash('success', 'Сервер успешно обновлен');
                        return $this->redirect(['index']);
                    }
                } catch (\yii\db\Exception $e) {
                    // Обработка ошибок базы данных, включая нарушение check constraint
                    $errorMessage = 'Ошибка сохранения в базе данных. ';
                    
                    if (strpos($e->getMessage(), 'Check constraint') !== false || strpos($e->getMessage(), 'servers_chk_1') !== false) {
                        $errorMessage .= 'Нарушение ограничений базы данных. Проверьте корректность введенных данных:';
                        $errorMessage .= '<ul>';
                        $errorMessage .= '<li>Даты вайпов должны быть в правильном порядке (последний вайп ≤ следующий вайп ≤ глобальный вайп)</li>';
                        $errorMessage .= '<li>Минимальный размер карты не должен быть больше максимального</li>';
                        $errorMessage .= '<li>Проверьте другие ограничения базы данных</li>';
                        $errorMessage .= '</ul>';
                        
                        // Добавляем ошибку к модели, чтобы она отобразилась в форме
                        $model->addError('name', $errorMessage);
                        
                        Yii::error('Check constraint violation: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
                    } else {
                        $errorMessage .= $e->getMessage();
                        $model->addError('name', $errorMessage);
                        Yii::error('Database error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
                    }
                    
                    // Также показываем flash сообщение
                    Yii::$app->session->setFlash('error', $errorMessage);
                } catch (\Exception $e) {
                    $errorMessage = 'Ошибка сохранения: ' . $e->getMessage();
                    $model->addError('name', $errorMessage);
                    Yii::$app->session->setFlash('error', $errorMessage);
                    Yii::error('Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
                }
            }
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
     * Очистка кэша для конкретного сервера
     * @param string $tag Тег сервера
     */
    protected function clearServersCache($tag)
    {
        Yii::$app->cache->delete('api_servers_index');
        Yii::$app->cache->delete('api_servers_view_' . $tag);
        Yii::$app->cache->delete('api_servers_rules_' . $tag);
    }

    /**
     * Очистка кэша для всех серверов
     */
    protected function clearAllServersCache()
    {
        Yii::$app->cache->delete('api_servers_index');
        
        // Получаем все теги серверов и сбрасываем их кэш
        $serverTags = Servers::find()->select('tag')->column();
        foreach ($serverTags as $tag) {
            Yii::$app->cache->delete('api_servers_view_' . $tag);
            Yii::$app->cache->delete('api_servers_rules_' . $tag);
        }
        
        // Сбрасываем кэш календаря вайпов
        $this->clearWipeCalendarCache();
    }

    /**
     * Очистка кэша календаря вайпов
     * Удаляет все возможные комбинации year_month_months
     */
    protected function clearMapsCache()
    {
        // Очищаем кэш зафиксированных карт
        Yii::$app->cache->delete('api_maps_fixed_ids');
    }

    protected function clearWipeCalendarCache()
    {
        // Удаляем кэш для текущего и ближайших месяцев (год назад и год вперед)
        $now = new \DateTime();
        $currentYear = (int)$now->format('Y');
        
        for ($year = $currentYear - 1; $year <= $currentYear + 1; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                for ($months = 1; $months <= 3; $months++) {
                    Yii::$app->cache->delete('api_wipe_calendar_' . $year . '_' . $month . '_' . $months);
                }
            }
        }
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
            
            // Сбрасываем кэш списка серверов
            Yii::$app->cache->delete('api_servers_index');
            
            // Сбрасываем кэш для всех серверов (так как сортировка влияет на список)
            $this->clearAllServersCache();
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
                    // Сбрасываем кэш календаря вайпов при изменении дат вайпов
                    if (!empty($wipe) || !empty($nextWipe) || !empty($globalWipe)) {
                        $this->clearWipeCalendarCache();
                    }
                    
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
