<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\components\StreamersLiveHelper;
use common\components\VideoMetadataFetcher;
use common\components\VideoPosterUploader;
use common\models\user\User;
use common\models\video\UserVideo;
use common\models\video\UserVideoLike;
use Yii;

/**
 * API видео (раздел Медиа): публичный список всех видео, добавление по ссылке (с авторизацией).
 * Поддерживаются YouTube и TikTok; название и превью подставляются автоматически.
 */
class UserVideoController extends BaseApiController
{
    /**
     * JWT требуется только для actionCreate. Для actionIndex — опционально (чтобы вернуть «мои» на модерации).
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['create', 'like'],
        ];
        $behaviors['optionalAuth'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['index'],
            'throwException' => false,
        ];
        return $behaviors;
    }

    /**
     * Список стримеров (is_blogger) с Twitch/Kick ссылками и статусом «В эфире».
     * GET /v1/user-videos/streamers
     */
    public function actionStreamers()
    {
        $query = User::find()
            ->joinWith(['userProfile'])
            ->where(['user.is_blogger' => 1])
            ->andWhere(
                '(user_profile.twitch_link IS NOT NULL AND TRIM(COALESCE(user_profile.twitch_link, "")) != "")' .
                ' OR (user_profile.kick_link IS NOT NULL AND TRIM(COALESCE(user_profile.kick_link, "")) != "")' .
                ' OR (user.twitch_id IS NOT NULL AND user.twitch_id != "")' .
                ' OR (user.kick_id IS NOT NULL AND user.kick_id != "")'
            )
            ->orderBy(['user.username' => SORT_ASC]);

        $users = $query->all();
        $items = [];
        foreach ($users as $user) {
            $twitchLink = $user->userProfile && trim((string) ($user->userProfile->twitch_link ?? '')) !== ''
                ? trim($user->userProfile->twitch_link)
                : ($user->twitch_id ? 'https://www.twitch.tv/' . $user->twitch_id : null);
            $kickLink = $user->userProfile && trim((string) ($user->userProfile->kick_link ?? '')) !== ''
                ? trim($user->userProfile->kick_link)
                : ($user->kick_id ? 'https://kick.com/channel/' . $user->kick_id : null);
            if ($twitchLink === null && $kickLink === null) {
                continue;
            }
            $isLive = false;
            if ($user->twitch_id) {
                $isLive = StreamersLiveHelper::isTwitchLive((string) $user->twitch_id);
            }
            if (!$isLive && $user->kick_id) {
                $isLive = StreamersLiveHelper::isKickLive((string) $user->kick_id);
            }
            $items[] = [
                'id' => $user->id,
                'username' => $user->username,
                'avatar' => $user->getAvatar(),
                'twitch_link' => $twitchLink,
                'kick_link' => $kickLink,
                'is_live' => $isLive,
            ];
        }
        return $this->successResponse(['streamers' => $items]);
    }

    /**
     * Список видео: только активные (опубликованные). Если передан JWT — дополнительно my_videos (на модерации/отклонённые).
     * GET /v1/user-videos?page=1&limit=20
     */
    public function actionIndex()
    {
        $page = (int) Yii::$app->request->get('page', 1);
        $limit = min(50, max(1, (int) Yii::$app->request->get('limit', 20)));

        $query = UserVideo::find()
            ->joinWith(['user'])
            ->andWhere(['user_video.status' => UserVideo::STATUS_ACTIVE])
            ->orderBy(['user_video.id' => SORT_DESC]);

        $total = $query->count();
        $offset = ($page - 1) * $limit;
        $list = $query->offset($offset)->limit($limit)->all();

        $likedVideoIds = [];
        if (!Yii::$app->user->isGuest && !empty($list)) {
            $ids = array_map(function (UserVideo $v) { return $v->id; }, $list);
            $likedVideoIds = UserVideoLike::find()
                ->where(['user_id' => Yii::$app->user->id, 'user_video_id' => $ids])
                ->select('user_video_id')
                ->column();
        }
        $items = array_map(function (UserVideo $v) use ($likedVideoIds) {
            return $this->formatVideoItem($v, $likedVideoIds);
        }, $list);

        $data = [
            'videos' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ];

        if (!Yii::$app->user->isGuest) {
            $myPending = UserVideo::find()
                ->joinWith(['user'])
                ->andWhere(['user_video.user_id' => Yii::$app->user->id])
                ->andWhere(['in', 'user_video.status', [UserVideo::STATUS_WAIT]])
                ->orderBy(['user_video.id' => SORT_DESC])
                ->all();
            $myIds = array_map(function (UserVideo $v) { return $v->id; }, $myPending);
            $myLikedIds = !empty($myIds) ? UserVideoLike::find()
                ->where(['user_id' => Yii::$app->user->id, 'user_video_id' => $myIds])
                ->select('user_video_id')
                ->column() : [];
            $data['my_videos'] = array_map(function (UserVideo $v) use ($myLikedIds) {
                return $this->formatVideoItem($v, $myLikedIds);
            }, $myPending);
        } else {
            $data['my_videos'] = [];
        }

        return $this->successResponse($data);
    }

    /**
     * @param UserVideo $v
     * @param int[] $likedVideoIds IDs видео, которым текущий пользователь поставил лайк
     */
    private function formatVideoItem(UserVideo $v, array $likedVideoIds = []): array
    {
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
            'likes' => (int) ($v->likes ?? 0),
            'is_liked' => in_array($v->id, $likedVideoIds, false),
        ];
        if ($v->user) {
            $row['username'] = $v->user->username;
            $row['avatar'] = $v->user->getAvatar();
        }
        return $row;
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
        $rawLink = trim(Yii::$app->request->post('video_link', ''));

        if ($rawLink === '') {
            return $this->errorResponse('VALIDATION_ERROR', 'Укажите ссылку на видео', ['video_link' => 'Ссылка обязательна'], 400);
        }

        $videoLink = VideoMetadataFetcher::normalizeUrl($rawLink);

        if (!VideoMetadataFetcher::isYouTubeUrl($videoLink) && !VideoMetadataFetcher::isTikTokUrl($videoLink)) {
            return $this->errorResponse('UNSUPPORTED_LINK', 'Поддерживаются только ссылки на YouTube и TikTok', ['video_link' => 'Недопустимая ссылка'], 400);
        }

        $meta = VideoMetadataFetcher::fetch($videoLink);
        if (empty($meta['name'])) {
            if (VideoMetadataFetcher::isTikTokUrl($videoLink)) {
                $meta = [
                    'type' => \common\models\video\UserVideo::TYPE_TIKTOK,
                    'name' => preg_match('#tiktok\.com/@([^/]+)/video/#i', $videoLink, $m) ? 'TikTok @' . $m[1] : 'TikTok video',
                    'poster_image' => null,
                    'poster_image_150' => null,
                    'poster_image_400' => null,
                ];
            } else {
                return $this->errorResponse('FETCH_ERROR', 'Не удалось получить данные видео. Проверьте ссылку.', [], 400);
            }
        }

        $posterUrls = null;
        $posterUrlFromMeta = $meta['poster_image'] ?? $meta['poster_image_400'] ?? '';
        if ($posterUrlFromMeta !== '' && Yii::$app->has('s3Api')) {
            $posterUrls = VideoPosterUploader::uploadPoster($posterUrlFromMeta);
        }
        if ($posterUrls !== null) {
            $meta['poster_image'] = $posterUrls['poster_image'];
            $meta['poster_image_150'] = $posterUrls['poster_image_150'];
            $meta['poster_image_400'] = $posterUrls['poster_image_400'];
        }

        if ($meta['type'] === UserVideo::TYPE_TIKTOK && Yii::$app->has('telegramChats')) {
            try {
                $log = 'UserVideo create TikTok: name=' . ($meta['name'] ?? '') . ' has_meta_poster=' . ($posterUrlFromMeta !== '' ? '1' : '0')
                    . ' upload_s3_ok=' . ($posterUrls !== null ? '1' : '0')
                    . ' link=' . $videoLink;
                Yii::$app->telegramChats->sendMessage($log);
            } catch (\Throwable $e) {
                Yii::warning('UserVideo telegramChats: ' . $e->getMessage(), __METHOD__);
            }
        }

        $videoName = trim((string) ($meta['name'] ?? ''));
        if ($videoName === '' && isset($meta['type']) && $meta['type'] === UserVideo::TYPE_TIKTOK) {
            $videoName = preg_match('#tiktok\.com/@([^/]+)/video/#i', $videoLink, $m) ? 'TikTok @' . $m[1] : 'TikTok video';
        }
        $videoName = $videoName !== '' ? $videoName : (string) ($meta['name'] ?? '');
        $videoName = mb_substr($videoName, 0, 255);

        $model = new UserVideo();
        $model->user_id = $user->id;
        $model->name = $videoName;
        $model->type = ($meta['type'] === UserVideo::TYPE_YOUTUBE && preg_match('#youtube\.com/shorts/#i', $rawLink))
            ? UserVideo::TYPE_SHORTS
            : $meta['type'];
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
            $text = "🎬 Новое видео на модерации: " . $model->name . "\nПользователь: " . $username . "\nСсылка: " . $model->video_link;
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

    /**
     * Список пользователей, поставивших лайк видео.
     * GET /v1/user-videos/{id}/likes
     */
    public function actionLikes($id)
    {
        $video = UserVideo::find()
            ->where(['id' => $id, 'status' => UserVideo::STATUS_ACTIVE])
            ->one();
        if (!$video) {
            return $this->errorResponse('NOT_FOUND', 'Видео не найдено', [], 404);
        }

        $page = (int) Yii::$app->request->get('page', 1);
        $limit = min(50, max(1, (int) Yii::$app->request->get('limit', 20)));

        $query = UserVideoLike::find()
            ->where(['user_video_id' => $id])
            ->with(['user']);

        $total = $query->count();
        $offset = ($page - 1) * $limit;
        $likes = $query->orderBy(['created_at' => SORT_DESC])->offset($offset)->limit($limit)->all();

        $users = [];
        foreach ($likes as $like) {
            if ($like->user) {
                $users[] = [
                    'id' => $like->user->id,
                    'username' => $like->user->username,
                    'steamId' => $like->user->steam_id,
                    'avatar' => $like->user->getAvatar(),
                    'likedAt' => $like->created_at,
                ];
            }
        }

        return $this->successResponse([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ]);
    }

    /**
     * Поставить или убрать лайк видео. Требует авторизации.
     * POST /v1/user-videos/{id}/like
     */
    public function actionLike($id)
    {
        $user = $this->getCurrentUser();

        $video = UserVideo::find()
            ->where(['id' => $id, 'status' => UserVideo::STATUS_ACTIVE])
            ->one();
        if (!$video) {
            return $this->errorResponse('NOT_FOUND', 'Видео не найдено', [], 404);
        }

        $userLike = UserVideoLike::find()
            ->where(['user_video_id' => $id, 'user_id' => $user->id])
            ->one();

        if ($userLike) {
            $userLike->delete();
            $video->likes = max(0, (int) $video->likes - 1);
            $video->save(false);
            $isLiked = false;
        } else {
            $like = new UserVideoLike();
            $like->user_id = $user->id;
            $like->user_video_id = (int) $id;
            $like->type = UserVideoLike::TYPE_LIKE;
            $like->created_at = date('Y-m-d H:i:s');
            if ($like->save()) {
                $video->likes = (int) $video->likes + 1;
                $video->save(false);
                $isLiked = true;
            } else {
                return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении лайка', $like->errors, 500);
            }
        }

        return $this->successResponse([
            'isLiked' => $isLiked,
            'likes' => (int) $video->likes,
        ]);
    }
}
