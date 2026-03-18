<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\components\VideoMetadataFetcher;
use common\models\video\UserVideo;
use Yii;

/**
 * API видео (раздел Медиа): публичный список всех видео, добавление по ссылке (с авторизацией).
 * Поддерживаются YouTube и TikTok; название и превью подставляются автоматически.
 */
class UserVideoController extends BaseApiController
{
    /**
     * JWT требуется только для actionCreate; actionIndex публичный.
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['create'],
        ];
        return $behaviors;
    }

    /**
     * Публичный список всех видео. Авторизация не требуется.
     * GET /v1/user-videos?page=1&limit=20
     */
    public function actionIndex()
    {
        $page = (int) Yii::$app->request->get('page', 1);
        $limit = min(50, max(1, (int) Yii::$app->request->get('limit', 20)));

        $query = UserVideo::find()
            ->joinWith(['user'])
            ->orderBy(['user_video.id' => SORT_DESC]);

        $total = $query->count();
        $offset = ($page - 1) * $limit;
        $list = $query->offset($offset)->limit($limit)->all();

        $items = array_map(function (UserVideo $v) {
            $row = [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'video_link' => $v->video_link,
                'poster_image' => $v->poster_image,
                'poster_image_150' => $v->poster_image_150,
                'poster_image_400' => $v->poster_image_400,
                'status' => $v->status,
                'status_label' => (UserVideo::getStatusList())[$v->status] ?? '',
                'created_at' => $v->created_at,
            ];
            if ($v->user) {
                $row['username'] = $v->user->username;
            }
            return $row;
        }, $list);

        return $this->successResponse([
            'videos' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ]);
    }

    /**
     * Добавить видео по ссылке (YouTube или TikTok).
     * POST /v1/user-videos/create
     * Body: { "video_link": "https://..." }
     * Метаданные (название, превью) подставляются автоматически; в Telegram уходит картинка, название и кнопки Принять/Отклонить.
     */
    public function actionCreate()
    {
        $user = $this->getCurrentUser();
        $videoLink = trim(Yii::$app->request->post('video_link', ''));

        if ($videoLink === '') {
            return $this->errorResponse('VALIDATION_ERROR', 'Укажите ссылку на видео', ['video_link' => 'Ссылка обязательна'], 400);
        }

        $videoLink = VideoMetadataFetcher::normalizeUrl($videoLink);

        if (!VideoMetadataFetcher::isYouTubeUrl($videoLink) && !VideoMetadataFetcher::isTikTokUrl($videoLink)) {
            return $this->errorResponse('UNSUPPORTED_LINK', 'Поддерживаются только ссылки на YouTube и TikTok', ['video_link' => 'Недопустимая ссылка'], 400);
        }

        $meta = VideoMetadataFetcher::fetch($videoLink);
        if (empty($meta['name'])) {
            return $this->errorResponse('FETCH_ERROR', 'Не удалось получить данные видео. Проверьте ссылку.', [], 400);
        }

        $model = new UserVideo();
        $model->user_id = $user->id;
        $model->name = $meta['name'];
        $model->type = $meta['type'];
        $model->video_link = $videoLink;
        $model->poster_image = $meta['poster_image'] ?? null;
        $model->poster_image_150 = $meta['poster_image_150'] ?? null;
        $model->poster_image_400 = $meta['poster_image_400'] ?? null;
        $model->status = UserVideo::STATUS_WAIT;

        if (!$model->save()) {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка сохранения', $model->errors, 500);
        }

        try {
            $username = $user->username ?? '—';
            $text = "🎬 Новое видео на модерации: " . $model->name . "\nПользователь: " . $username;
            $posterUrl = $model->poster_image_400 ?: $model->poster_image;
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
            Yii::error('UserVideo telegram: ' . $e->getMessage(), __METHOD__);
        }

        try {
            if (!empty($user->telegram_chat_id)) {
                Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, '🎬 Видео отправлено на модерацию. Мы принимаем только ролики, связанные с нашими серверами.');
            }
        } catch (\Exception $e) {
            Yii::error('UserVideo personal telegram: ' . $e->getMessage(), __METHOD__);
        }

        return $this->successResponse([
            'id' => $model->id,
            'name' => $model->name,
            'type' => $model->type,
            'video_link' => $model->video_link,
            'poster_image' => $model->poster_image,
            'poster_image_150' => $model->poster_image_150,
            'poster_image_400' => $model->poster_image_400,
            'status' => $model->status,
            'created_at' => $model->created_at,
        ], [], 201);
    }
}
