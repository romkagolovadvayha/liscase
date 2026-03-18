<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\video\UserVideo;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * VideoController implements the CRUD actions for UserVideo model.
 */
class VideoController extends Controller
{
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
                    'class' => VerbFilter::class,
                    'actions' => [
                        'delete' => ['POST'],
                        'success' => ['POST'],
                        'reject' => ['POST'],
                    ],
                ],
            ]
        );
    }

    public function actionIndex()
    {
        $searchModel = new \backend\models\video\UserVideoSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить видео'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new UserVideo();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                $this->sendVideoToTelegramIfWait($model);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
            $model->status = UserVideo::STATUS_WAIT;
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

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $oldStatus = $model->getOldAttribute('status');

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            if ($model->status === UserVideo::STATUS_WAIT && $oldStatus !== UserVideo::STATUS_WAIT) {
                $this->sendVideoToTelegramIfWait($model);
            }
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

        $model->status = UserVideo::STATUS_ACTIVE;
        if ($model->save(false)) {
            if ($model->user && !empty($model->user->telegram_chat_id)) {
                try {
                    Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '🎬 Ваше видео успешно прошло модерацию и опубликовано!');
                } catch (\Exception $e) {
                    Yii::error('Video success telegram: ' . $e->getMessage(), __METHOD__);
                }
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', ['model' => $model]);
    }

    public function actionReject($id)
    {
        $model = $this->findModel($id);
        $wasActive = $model->status === UserVideo::STATUS_ACTIVE;

        $model->status = UserVideo::STATUS_REJECT;
        if ($model->save(false)) {
            if (!$wasActive && $model->user && !empty($model->user->telegram_chat_id)) {
                try {
                    Yii::$app->personalBotTelegram->sendMessage($model->user->telegram_chat_id, '🎬 Ваше видео не прошло модерацию.');
                } catch (\Exception $e) {
                    Yii::error('Video reject telegram: ' . $e->getMessage(), __METHOD__);
                }
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('view', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->addFlash('success', Yii::t('common', 'Запись успешно удалена!'));
        return $this->redirect(['index']);
    }

    /**
     * Отправка уведомления в Telegram при создании/постановке видео на модерацию.
     */
    protected function sendVideoToTelegramIfWait(UserVideo $model)
    {
        if ($model->status !== UserVideo::STATUS_WAIT) {
            return;
        }
        try {
            $username = $model->user ? $model->user->username : '—';
            $text = "🎬 Новое видео на модерации: " . $model->name . "\nПользователь: " . $username;
            $posterUrl = !empty($model->poster_image_400) ? $model->poster_image_400 : (!empty($model->poster_image) ? $model->poster_image : null);
            Yii::$app->telegramSupport->sendMessage(
                $text,
                [
                    [
                        'text' => '🟢 Принять',
                        'callback_data' => json_encode([
                            'action' => 'success-video',
                            'video_id' => $model->id,
                        ]),
                    ],
                    [
                        'text' => '🔴 Отклонить',
                        'callback_data' => json_encode([
                            'action' => 'reject-video',
                            'video_id' => $model->id,
                        ]),
                    ],
                ],
                $posterUrl
            );
        } catch (\Exception $e) {
            Yii::error('Video telegram moderation: ' . $e->getMessage(), __METHOD__);
        }
    }

    protected function findModel($id)
    {
        $model = UserVideo::findOne(['id' => $id]);
        if ($model !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
