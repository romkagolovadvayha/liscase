<?php

namespace backend\controllers;

use common\models\radio\RadioTrack;
use common\models\radio\RadioStation;
use backend\models\radio\RadioTrackSearch;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * RadioController implements the CRUD and moderation actions for Radio models.
 */
class RadioController extends Controller
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
                        'delete-station' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Main index page with tabs for stations and tracks
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }
    
    /**
     * Lists all RadioTrack models (tracks moderation)
     *
     * @return string
     */
    public function actionTracks()
    {
        $searchModel = new RadioTrackSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('tracks/index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
    
    /**
     * Lists all RadioStation models
     *
     * @return string
     */
    public function actionStations()
    {
        $stations = RadioStation::find()->orderBy(['id' => SORT_ASC])->all();

        return $this->render('stations/index', [
            'stations' => $stations,
        ]);
    }
    
    /**
     * View/create/update a radio station
     *
     * @param int|null $id Station ID (null for create)
     * @return string|\yii\web\Response
     */
    public function actionStation($id = null)
    {
        if ($id === null) {
            $model = new RadioStation();
        } else {
            $model = $this->findStation($id);
        }

        if ($this->request->isPost && $model->load($this->request->post())) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', Yii::t('common', 'Радиостанция успешно сохранена!'));
                return $this->redirect(['stations']);
            }
        }

        return $this->render('stations/view', [
            'model' => $model,
        ]);
    }
    
    /**
     * Delete a radio station
     *
     * @param int $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionDeleteStation($id)
    {
        $model = $this->findStation($id);
        
        // Проверяем что нет треков на этой станции
        $tracksCount = $model->getRadioTracks()->count();
        if ($tracksCount > 0) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Невозможно удалить радиостанцию: на ней есть треки ({count}).', ['count' => $tracksCount]));
            return $this->redirect(['stations']);
        }
        
        $model->delete();
        
        Yii::$app->session->setFlash('success', Yii::t('common', 'Радиостанция успешно удалена!'));
        return $this->redirect(['stations']);
    }

    /**
     * Displays a single RadioTrack model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('tracks/view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Approves a track (sets status to ACTIVE)
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionSuccess($id)
    {
        $model = $this->findModel($id);

        // Получаем актуальную длительность из Node.js API
        if ($model->radioStation && $model->filename) {
            $duration = $this->getAudioDurationFromNode($model->radioStation, $model->filename);
            if ($duration !== null) {
                $model->duration = $duration;
            }
        }

        $model->status = RadioTrack::STATUS_ACTIVE;
        if ($model->save()) {
            // Автоматически добавить трек в очередь Node.js
            $this->addTrackToNodeQueue($model);
            
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage(
                    $model->user->telegram_chat_id,
                    '🎵 Ваш трек "' . $model->title . '" успешно прошёл модерацию и добавлен на радиостанцию!'
                );
            }
            Yii::$app->session->addFlash('success', Yii::t('common', 'Трек одобрен и добавлен в очередь!'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }
    
    /**
     * Add approved track to Node.js queue
     * @param RadioTrack $track
     * @return bool
     */
    protected function addTrackToNodeQueue($track)
    {
        if (!$track->radioStation) {
            Yii::warning("Track {$track->id} has no station", __METHOD__);
            return false;
        }
        
        $station = $track->radioStation;
        
        try {
            // Перезагружаем весь плейлист через GET запрос
            $nodeApiUrl = "http://localhost:{$station->port}/api/reload";
            
            Yii::info("Reloading Node.js playlist: {$nodeApiUrl}", __METHOD__);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true,
                ]
            ]);
            
            $nodeResponse = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($nodeResponse === false) {
                Yii::warning("Node.js server not responding at {$nodeApiUrl}. Track will be added on next restart.", __METHOD__);
                return false;
            }
            
            Yii::info("Node.js response: {$nodeResponse}", __METHOD__);
            
            $nodeData = json_decode($nodeResponse, true);
            
            if (isset($nodeData['success']) && $nodeData['success']) {
                Yii::info("✅ Playlist reloaded for station {$station->id}, tracks count: " . ($nodeData['tracksCount'] ?? 'N/A'), __METHOD__);
                return true;
            } else {
                Yii::warning("❌ Failed to reload playlist: " . ($nodeData['error'] ?? 'unknown error'), __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("❌ Node.js API exception: " . $e->getMessage(), __METHOD__);
        }
        
        return false;
    }

    /**
     * Rejects a track (sets status to REJECT)
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionReject($id)
    {
        $model = $this->findModel($id);

        $model->status = RadioTrack::STATUS_REJECT;
        if ($model->save()) {
            if (!empty($model->user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage(
                    $model->user->telegram_chat_id,
                    '🎵 Ваш трек "' . $model->title . '" не прошёл модерацию.'
                );
            }
            Yii::$app->session->addFlash('warning', Yii::t('common', 'Трек отклонён!'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing RadioTrack model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        
        // Сохраняем данные для Telegram перед удалением
        $trackTitle = $model->title;
        $user = $model->user;
        $filename = $model->filename;
        $station = $model->radioStation;

        // Удаление трека из очереди Node.js
        if ($station && $filename) {
            $this->removeTrackFromNodeQueue($station, $filename);
        }

        // Удаление файла
        $filePath = $model->getFilePath();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Удаление лайков
        foreach ($model->radioTrackLikes as $like) {
            $like->delete();
        }

        // Отправка уведомления в Telegram пользователю
        if (!empty($user->telegram_chat_id)) {
            Yii::$app->personalBotTelegram->sendMessage(
                $user->telegram_chat_id,
                '🎵 Ваш трек "' . $trackTitle . '" был удалён модератором с радиостанции.'
            );
        }
        
        $model->delete();

        Yii::$app->session->addFlash('success', Yii::t('common', 'Трек успешно удалён!'));
        return $this->redirect(['index']);
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
     * Remove track from Node.js queue
     * @param \common\models\radio\RadioStation $station
     * @param string $filename
     * @return bool
     */
    protected function removeTrackFromNodeQueue($station, $filename)
    {
        try {
            $nodeApiUrl = "http://localhost:{$station->port}/api/queue/remove-by-filename";
            
            Yii::info("Removing track from Node.js queue: {$filename}", __METHOD__);
            
            $postData = json_encode(['track' => $filename]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json' . "\r\n" .
                                'Content-Length: ' . strlen($postData) . "\r\n",
                    'content' => $postData,
                    'timeout' => 3,
                    'ignore_errors' => true,
                ]
            ]);
            
            $nodeResponse = @file_get_contents($nodeApiUrl, false, $context);
            
            if ($nodeResponse === false) {
                Yii::warning("Node.js server not responding at {$nodeApiUrl}", __METHOD__);
                return false;
            }
            
            $nodeData = json_decode($nodeResponse, true);
            
            if (isset($nodeData['success']) && $nodeData['success']) {
                Yii::info("✅ Track removed from queue for station {$station->id}", __METHOD__);
                return true;
            } else {
                Yii::warning("❌ Failed to remove track from queue: " . ($nodeData['error'] ?? 'unknown error'), __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("❌ Node.js API exception: " . $e->getMessage(), __METHOD__);
        }
        
        return false;
    }

    /**
     * Finds the RadioTrack model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return RadioTrack the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = RadioTrack::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
    
    /**
     * Finds the RadioStation model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return RadioStation the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findStation($id)
    {
        if (($model = RadioStation::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}

