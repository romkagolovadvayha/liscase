<?php

namespace frontend\controllers;

use common\models\radio\RadioStation;
use common\models\radio\RadioTrack;
use common\models\radio\RadioTrackLike;
use frontend\models\radio\RadioTrackSearch;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;

/**
 * RadioController for frontend users
 */
class RadioController extends Controller
{
    public $enableCsrfValidation = true; // явно включаем
    
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
                            'actions' => ['index', 'station', 'station-status', 'now-playing', 'get-likes'],
                            'roles' => ['?', '@'], // Страницы доступны всем
                        ],
                        [
                            'allow' => true,
                            'actions' => ['queue', 'update-current', 'update-listeners'],
                            'roles' => ['?', '@'], // API доступно всем
                        ],
                        [
                            'allow' => true,
                            'roles' => ['@'], // Остальные действия требуют авторизации
                        ],
                    ],
                ],
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                        'like' => ['POST'],
                    ],
                ],
            ]
        );
    }
    
    public function beforeAction($action)
    {
        // Отключаем CSRF для API endpoints (вызываются из Node.js и AJAX)
        if (in_array($action->id, ['queue', 'update-current', 'update-listeners', 'now-playing', 'station-status', 'get-likes'])) {
            $this->enableCsrfValidation = false;
        }
        
        // Для отладки - временно отключим CSRF для create
        if ($action->id === 'create' && Yii::$app->request->isPost) {
            $this->enableCsrfValidation = false;
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Lists all active radio stations
     *
     * @return string
     */
    public function actionIndex()
    {
        // if (!Yii::$app->settings->get('site_section_radio')) {
        //     throw new NotFoundHttpException('The requested page does not exist.');
        // }

        $stations = RadioStation::find()
            ->where(['radio_station.status' => RadioStation::STATUS_ACTIVE])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'stations' => $stations,
        ]);
    }

    /**
     * Displays tracks for a specific radio station
     * @param int $id Station ID
     * @return string
     * @throws NotFoundHttpException if the station cannot be found
     */
    public function actionStation($id)
    {
        $station = $this->findStation($id);
        $searchModel = new RadioTrackSearch();
        $dataProvider = $searchModel->search($this->request->queryParams, $id);

        // Check if user has tracks waiting moderation (only for logged in users)
        $userTracksWait = false;
        if (!Yii::$app->user->isGuest) {
            $userTracksWait = RadioTrack::find()
                ->andWhere(['user_id' => Yii::$app->user->id])
                ->andWhere(['radio_station_id' => $id])
                ->andWhere(['radio_track.status' => RadioTrack::STATUS_WAIT])
                ->exists();
        }

        return $this->render('station', [
            'station' => $station,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'userTracksWait' => $userTracksWait,
        ]);
    }

    /**
     * Creates a new RadioTrack
     * @param int $stationId Station ID
     * @return string|\yii\web\Response
     */
    public function actionCreate($stationId)
    {
        $station = $this->findStation($stationId);
        $model = new RadioTrack(['scenario' => 'create']);
        $model->radio_station_id = $stationId;
        $model->user_id = Yii::$app->user->id;
        $model->status = RadioTrack::STATUS_WAIT;

        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                $model->audioFile = UploadedFile::getInstance($model, 'audioFile');
                
                if ($model->audioFile && $model->validate(['title', 'artist', 'audioFile'])) {
                    // Generate unique filename
                    $filename = Yii::$app->security->generateRandomString(16) . '.mp3';
                    $uploadPath = $station->getFolderPath();
                    
                    // Create folder if not exists
                    if (!file_exists($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    
                    $filePath = $uploadPath . '/' . $filename;
                    
                    if ($model->audioFile->saveAs($filePath)) {
                        $model->filename = $filename;
                        
                        // Try to get duration from Node.js API first
                        $duration = $this->getAudioDurationFromNode($station, $filename);
                        
                        // Fallback to local methods if Node.js not available
                        if ($duration === null) {
                            $duration = $this->getAudioDuration($filePath);
                        }
                        
                        $model->duration = $duration !== null ? $duration : 0;
                        
                        if ($model->save(false)) { // skip validation, already validated
                            // Отправляем уведомление в Telegram с аудио файлом
                            $caption = "🎵 Новый трек отправлен на модерацию!" . PHP_EOL .
                                PHP_EOL .
                                "📻 Радиостанция: {$station->name}" . PHP_EOL .
                                "🎤 Трек: {$model->title}" . ($model->artist ? " - {$model->artist}" : "") . PHP_EOL .
                                "👤 Пользователь: {$model->user->username}";
                            
                            // Отправляем аудио файл в Telegram
                            try {
                                $audioResult = Yii::$app->telegramSupport->sendAudio(
                                    $filePath,
                                    $caption,
                                    [
                                        [
                                            'text' => '🟢 Принять',
                                            'callback_data' => json_encode([
                                                'action' => 'success-track',
                                                'track_id' => $model->id,
                                            ])
                                        ],
                                        [
                                            'text' => '🔴 Отклонить',
                                            'callback_data' => json_encode([
                                                'action' => 'reject-track',
                                                'track_id' => $model->id,
                                            ])
                                        ]
                                    ]
                                );
                                
                                // Если не удалось отправить аудио, отправляем текстовое сообщение
                                if (!$audioResult || (isset($audioResult['ok']) && !$audioResult['ok'])) {
                                    Yii::$app->telegramSupport->sendMessage(
                                        $caption,
                                        [
                                            [
                                                'text' => '🟢 Принять',
                                                'callback_data' => json_encode([
                                                    'action' => 'success-track',
                                                    'track_id' => $model->id,
                                                ])
                                            ],
                                            [
                                                'text' => '🔴 Отклонить',
                                                'callback_data' => json_encode([
                                                    'action' => 'reject-track',
                                                    'track_id' => $model->id,
                                                ])
                                            ]
                                        ]
                                    );
                                }
                            } catch (\Exception $e) {
                                Yii::error("Failed to send audio to Telegram: " . $e->getMessage(), __METHOD__);
                            }
                            
                            Yii::$app->session->setFlash('success', Yii::t('common', 'Трек успешно загружен и отправлен на модерацию!'));
                            return $this->redirect(['station', 'id' => $stationId]);
                        } else {
                            // Delete file if save failed
                            @unlink($filePath);
                            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при сохранении трека в базу данных.'));
                        }
                    } else {
                        Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при загрузке файла.'));
                    }
                } else {
                    if (!$model->audioFile) {
                        Yii::$app->session->setFlash('error', Yii::t('common', 'Выберите MP3 файл для загрузки.'));
                    } else {
                        Yii::$app->session->setFlash('error', Yii::t('common', 'Проверьте правильность заполнения формы.'));
                    }
                }
            }
        }

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('create', [
                'model' => $model,
                'station' => $station,
            ]);
        }

        return $this->render('create', [
            'model' => $model,
            'station' => $station,
        ]);
    }

    /**
     * Get users who liked a track
     * @param int $id Track ID
     * @return array
     */
    public function actionGetLikes($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $track = $this->findTrack($id);
        
        $likes = \common\models\radio\RadioTrackLike::find()
            ->where(['radio_track_id' => $id])
            ->with(['user'])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(20)
            ->all();
        
        $users = [];
        foreach ($likes as $like) {
            $users[] = [
                'username' => $like->user->username,
                'avatar' => $like->user->getAvatar(),
            ];
        }
        
        return [
            'users' => $users,
            'total' => count($users),
        ];
    }

    /**
     * Like/Unlike a track
     * @param int $id Track ID (from GET or POST)
     * @return \yii\web\Response
     */
    public function actionLike($id = null)
    {
        // Get id from POST if not in URL
        if ($id === null) {
            $id = Yii::$app->request->post('id');
        }
        
        if (!$id) {
            throw new NotFoundHttpException('Track ID is required.');
        }
        
        $track = $this->findTrack($id);
        
        $like = RadioTrackLike::findOne([
            'radio_track_id' => $id,
            'user_id' => Yii::$app->user->id,
        ]);

        if ($like) {
            // Unlike
            $like->delete();
            $track->likes = max(0, $track->likes - 1);
            $track->save(false);
            $liked = false;
        } else {
            // Like
            $like = new RadioTrackLike();
            $like->radio_track_id = $id;
            $like->user_id = Yii::$app->user->id;
            // type не используется - колонки нет в таблице
            
            if ($like->save()) {
                $track->likes++;
                $track->save(false);
                $liked = true;
            }
        }

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return [
                'success' => true,
                'liked' => $liked ?? false,
                'likes' => $track->likes,
            ];
        }

        return $this->redirect(Yii::$app->request->referrer ?: ['station', 'id' => $track->radio_station_id]);
    }

    /**
     * Delete own track
     * @param int $id Track ID
     * @return \yii\web\Response
     */
    public function actionDelete($id)
    {
        $model = $this->findTrack($id);
        
        // Check if user owns this track
        if ($model->user_id != Yii::$app->user->id) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $stationId = $model->radio_station_id;

        // Delete file
        $filePath = $model->getFilePath();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete likes
        foreach ($model->radioTrackLikes as $like) {
            $like->delete();
        }

        $model->delete();

        Yii::$app->session->setFlash('success', Yii::t('common', 'Трек успешно удалён!'));
        return $this->redirect(['station', 'id' => $stationId]);
    }

    /**
     * Get current queue for a station (API endpoint for Node.js)
     * @param int $id Station ID
     * @return array
     */
    public function actionQueue($id)
    {
        $station = $this->findStation($id);
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Get approved tracks for this station
        $tracks = RadioTrack::find()
            ->where(['radio_track.radio_station_id' => $id, 'radio_track.status' => RadioTrack::STATUS_ACTIVE])
            ->orderBy(['id' => SORT_ASC])
            ->with(['user'])
            ->all();

        $queue = [];
        foreach ($tracks as $track) {
            $queue[] = [
                'id' => $track->id,
                'title' => $track->title,
                'artist' => $track->artist,
                'filename' => $track->filename,
                'filepath' => $track->getFilePath(), // полный путь к файлу
                'duration' => $track->duration,
                'formattedDuration' => $track->getFormattedDuration(),
                'likes' => $track->likes,
                'plays' => $track->plays,
                'user' => [
                    'id' => $track->user->id,
                    'username' => $track->user->username,
                    'avatar' => $track->user->getAvatar(),
                ],
            ];
        }

        return [
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'port' => $station->port,
            ],
            'queue' => $queue,
            'total' => count($queue),
        ];
    }

    /**
     * Update current track (called from Node.js)
     * @param int $id Station ID
     * @return array
     */
    public function actionUpdateCurrent($id)
    {
        $station = $this->findStation($id);
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $trackId = Yii::$app->request->post('trackId');
        
        if ($trackId) {
            $station->current_track_id = $trackId;
            $station->save(false);
            
            // Increment play count
            $track = RadioTrack::findOne($trackId);
            if ($track) {
                $track->plays++;
                $track->save(false);
            }
        }
        
        return ['success' => true];
    }

    /**
     * Update listeners count (called from Node.js)
     * @param int $id Station ID
     * @return array
     */
    public function actionUpdateListeners($id)
    {
        $station = $this->findStation($id);
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $count = Yii::$app->request->post('count', 0);
        
        $station->listeners_count = $count;
        $station->is_running = $count > 0 || Yii::$app->request->post('isRunning', false);
        $station->save(false);
        
        return ['success' => true, 'listeners' => $count];
    }

    /**
     * Pay and add track first to queue
     */
    public function actionQueueFirstPay()
    {
        $id = Yii::$app->request->get('id');
        $track = $this->findTrack($id);
        $user = Yii::$app->user->identity;
        
        $price = 15; // 15 рублей
        
        if ($this->request->isPost) {
            // Проверяем баланс
            $balance = $user->getPersonalBalance();
            
            if ($balance->balance < $price) {
                Yii::$app->session->setFlash('error', Yii::t('common', 'Недостаточно средств. Требуется: {price} монет', ['price' => $price]));
                return $this->refresh();
            }
            
            // Списываем деньги
            \common\models\invoice\Invoice::createRecord(
                $user->id,
                $price,
                \common\models\invoice\Invoice::TYPE_PAYMENT_RADIO_FIRST,
                null,
                null,
                null,
                Yii::t('common', 'Постановка трека первым в очередь: {title}', ['title' => $track->title])
            );
            
            // Добавляем в очередь Node.js
            $station = $track->radioStation;
            $nodeApiUrl = "http://localhost:{$station->port}/api/queue/add-first?track=" . urlencode($track->filename);
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true,
                ]
            ]);
            
            $nodeResponse = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($nodeResponse) {
                $nodeData = json_decode($nodeResponse, true);
                if (isset($nodeData['success']) && $nodeData['success']) {
                    Yii::$app->session->setFlash('success', Yii::t('common', 'Трек успешно поставлен первым в очередь! Списанно: {price} монет', ['price' => $price]));
                    
                    if (Yii::$app->request->isAjax) {
                        return $this->redirect(Yii::$app->request->referrer);
                    }
                    return $this->redirect(['station', 'id' => $station->id]);
                }
            }
            
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при добавлении в очередь'));
        }
        
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('queue-first-pay', [
                'track' => $track,
                'price' => $price,
            ]);
        }
        
        return $this->render('queue-first-pay', [
            'track' => $track,
            'price' => $price,
        ]);
    }
    
    /**
     * Add track first to queue via Node.js API
     * @return array
     */
    public function actionQueueFirst()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $stationId = Yii::$app->request->post('station_id');
        $filename = Yii::$app->request->post('filename');
        
        if (!$stationId || !$filename) {
            return ['success' => false, 'error' => 'Missing parameters'];
        }
        
        $station = $this->findStation($stationId);
        
        // Проверяем что трек существует
        $track = RadioTrack::find()
            ->where(['filename' => $filename, 'radio_station_id' => $stationId, 'status' => RadioTrack::STATUS_ACTIVE])
            ->one();
        
        if (!$track) {
            return ['success' => false, 'error' => 'Track not found'];
        }
        
        // Отправляем запрос в Node.js через GET
        try {
            $nodeApiUrl = "http://localhost:{$station->port}/api/queue/add-first?track=" . urlencode($filename);
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true,
                ]
            ]);
            
            $nodeResponse = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($nodeResponse) {
                $nodeData = json_decode($nodeResponse, true);
                
                if (isset($nodeData['success']) && $nodeData['success']) {
                    return ['success' => true, 'message' => 'Track added to front of queue'];
                }
            }
            
            return ['success' => false, 'error' => 'Node.js API error'];
        } catch (\Exception $e) {
            Yii::error("Node.js API error: " . $e->getMessage(), __METHOD__);
            return ['success' => false, 'error' => 'Connection error'];
        }
    }

    /**
     * Get station status (for main page updates)
     * @param int $id Station ID
     * @return array
     */
    public function actionStationStatus($id)
    {
        $station = $this->findStation($id);
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $result = [
            'is_running' => false,
            'listeners_count' => 0,
        ];
        
        // Получаем данные из Node.js API
        try {
            $nodeApiUrl = "http://localhost:{$station->port}/api/status";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true,
                ]
            ]);
            
            $nodeResponse = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($nodeResponse) {
                $nodeData = json_decode($nodeResponse, true);
                
                if ($nodeData && $nodeData['status'] === 'running') {
                    $result['is_running'] = true;
                    $result['listeners_count'] = $nodeData['listeners'] ?? 0;
                    
                    // Обновить в БД
                    if ($station->is_running != 1 || $station->listeners_count != $result['listeners_count']) {
                        $station->is_running = 1;
                        $station->listeners_count = $result['listeners_count'];
                        $station->save(false);
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Node.js API error: " . $e->getMessage(), __METHOD__);
        }
        
        return $result;
    }

    /**
     * Get current now playing info (for frontend widget)
     * Получает данные из Node.js REST API
     * @param int $id Station ID
     * @return array
     */
    public function actionNowPlaying($id)
    {
        $station = $this->findStation($id);
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $result = [
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'is_running' => false,
                'listeners_count' => 0,
            ],
            'current' => null,
            'queue' => [],
        ];
        
        // Получаем данные из Node.js API
        try {
            $nodeApiUrl = "http://localhost:{$station->port}/api/status";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2, // 2 секунды таймаут
                    'ignore_errors' => true,
                ]
            ]);
            
            $nodeResponse = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($nodeResponse) {
                $nodeData = json_decode($nodeResponse, true);
                
                if ($nodeData && $nodeData['status'] === 'running') {
                    $result['station']['is_running'] = true;
                    $result['station']['listeners_count'] = $nodeData['listeners'] ?? 0;
                    
                    // Текущий трек из Node.js
                    if (!empty($nodeData['current']['name'])) {
                        $currentFilename = $nodeData['current']['name'];
                        
                        // Найти трек в БД по filename
                        $currentTrack = RadioTrack::find()
                            ->where(['filename' => $currentFilename, 'radio_station_id' => $id])
                            ->with(['user'])
                            ->one();
                        
                        if ($currentTrack) {
                            $result['current'] = [
                                'id' => $currentTrack->id,
                                'title' => $currentTrack->title,
                                'artist' => $currentTrack->artist,
                                'duration' => $currentTrack->getFormattedDuration(),
                                'likes' => $currentTrack->likes,
                                'plays' => $currentTrack->plays,
                                'user' => [
                                    'username' => $currentTrack->user->username,
                                    'avatar' => $currentTrack->user->getAvatar(),
                                ],
                            ];
                            
                            // Обновить в БД если изменилось
                            if ($station->current_track_id != $currentTrack->id) {
                                $station->current_track_id = $currentTrack->id;
                                $station->save(false);
                            }
                        }
                    }
                    
                    // Очередь из Node.js
                    if (!empty($nodeData['queue']) && is_array($nodeData['queue'])) {
                        foreach (array_slice($nodeData['queue'], 0, 5) as $queueItem) {
                            $queueFilename = $queueItem['name'] ?? null;
                            
                            if ($queueFilename) {
                                $queueTrack = RadioTrack::find()
                                    ->where(['filename' => $queueFilename, 'radio_station_id' => $id])
                                    ->with(['user'])
                                    ->one();
                                
                                if ($queueTrack) {
                                    $result['queue'][] = [
                                        'id' => $queueTrack->id,
                                        'title' => $queueTrack->title,
                                        'artist' => $queueTrack->artist,
                                        'duration' => $queueTrack->getFormattedDuration(),
                                        'user' => [
                                            'username' => $queueTrack->user->username,
                                            'avatar' => $queueTrack->user->getAvatar(),
                                        ],
                                    ];
                                }
                            }
                        }
                    }
                    
                    // Обновить listeners_count в БД
                    if ($station->listeners_count != $result['station']['listeners_count']) {
                        $station->listeners_count = $result['station']['listeners_count'];
                        $station->is_running = true;
                        $station->save(false);
                    }
                }
            }
        } catch (\Exception $e) {
            // Node.js недоступен - возвращаем данные из БД как fallback
            Yii::error("Node.js API error: " . $e->getMessage(), __METHOD__);
        }
        
        return $result;
    }

    /**
     * Finds the RadioStation model based on its primary key value.
     */
    protected function findStation($id)
    {
        if (($model = RadioStation::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Finds the RadioTrack model based on its primary key value.
     */
    protected function findTrack($id)
    {
        if (($model = RadioTrack::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Get audio duration from Node.js API
     * @param \common\models\radio\RadioStation $station
     * @param string $filename
     * @return int|null
     */
    protected function getAudioDurationFromNode($station, $filename)
    {
        try {
            $nodeApiUrl = "http://localhost:{$station->port}/api/track-info?track=" . urlencode($filename);
            
            Yii::info("Getting duration from Node.js: {$nodeApiUrl}", __METHOD__);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                ]
            ]);
            
            $response = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['success']) && $data['success'] && isset($data['duration'])) {
                    Yii::info("✅ Got duration from Node.js: {$data['duration']}s for {$filename}", __METHOD__);
                    return (int)$data['duration'];
                } else {
                    Yii::warning("Node.js returned error: " . ($data['error'] ?? 'unknown'), __METHOD__);
                }
            }
        } catch (\Exception $e) {
            Yii::warning("Node.js API error: " . $e->getMessage(), __METHOD__);
        }
        
        return null;
    }

    /**
     * Get audio duration in seconds (fallback method)
     * @param string $filePath
     * @return int|null
     */
    protected function getAudioDuration($filePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        // Try using ffprobe if available
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // Windows: try ffprobe directly
            $ffprobePaths = [
                'ffprobe',
                'C:\\ffmpeg\\bin\\ffprobe.exe',
                'C:\\Program Files\\ffmpeg\\bin\\ffprobe.exe',
            ];
            
            foreach ($ffprobePaths as $ffprobe) {
                $cmd = $ffprobe . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filePath) . ' 2>NUL';
                $duration = trim(@shell_exec($cmd));
                if (is_numeric($duration) && $duration > 0) {
                    return (int)round($duration);
                }
            }
        } else {
            // Linux/Mac: use which
            $ffprobe = trim(@shell_exec('which ffprobe 2>/dev/null'));
            if (!empty($ffprobe) && file_exists($ffprobe)) {
                $cmd = escapeshellcmd($ffprobe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filePath);
                $duration = trim(@shell_exec($cmd));
                if (is_numeric($duration) && $duration > 0) {
                    return (int)round($duration);
                }
            }
        }

        // Try getID3 library if available
        if (class_exists('getID3')) {
            try {
                $getID3 = new \getID3();
                $info = $getID3->analyze($filePath);
                if (isset($info['playtime_seconds'])) {
                    return (int)round($info['playtime_seconds']);
                }
            } catch (\Exception $e) {
                Yii::error("getID3 error: " . $e->getMessage(), __METHOD__);
            }
        }

        // Fallback: estimate from file size (approximate, MP3 typically 128-320 kbps)
        $fileSize = filesize($filePath);
        if ($fileSize) {
            // Assume average 192 kbps = 24000 bytes per second
            $estimated = (int)round($fileSize / 24000);
            Yii::warning("Could not get exact duration for {$filePath}, estimated: {$estimated}s from file size", __METHOD__);
            return $estimated;
        }

        return null;
    }
}

