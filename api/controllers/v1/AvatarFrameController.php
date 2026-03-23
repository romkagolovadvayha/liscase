<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use common\models\avatar\AvatarFrame;
use common\models\user\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

class AvatarFrameController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['options', 'index'],
            'throwException' => false,
        ];

        return $behaviors;
    }

    /**
     * GET /v1/avatar-frames
     */
    public function actionIndex()
    {
        $items = [];
        foreach (AvatarFrame::getActiveOrdered() as $frame) {
            $items[] = [
                'id' => (int)$frame->id,
                'name' => $frame->name,
                'image_url' => $frame->getImageUrl(),
                'sort' => (int)$frame->sort,
            ];
        }

        return $this->successResponse([
            'items' => $items,
        ]);
    }

    /**
     * POST /v1/avatar-frames/select
     */
    public function actionSelect()
    {
        /** @var User|null $user */
        $user = Yii::$app->user->identity;
        if (!$user) {
            throw new UnauthorizedHttpException('Authentication required');
        }
        if (!$user->hasVip()) {
            throw new ForbiddenHttpException('VIP required');
        }

        $body = Yii::$app->request->getBodyParams();
        $frameId = array_key_exists('frame_id', $body) ? (int)$body['frame_id'] : null;
        if ($frameId === null) {
            throw new BadRequestHttpException('frame_id is required');
        }

        if ($frameId === 0) {
            $user->avatar_frame = null;
            $user->save(false, ['avatar_frame']);
            return $this->successResponse(['avatar_frame_id' => null, 'avatar_frame_url' => null]);
        }

        $frame = AvatarFrame::find()->where(['id' => $frameId, 'is_active' => 1])->one();
        if (!$frame) {
            throw new BadRequestHttpException('Frame not found');
        }

        $user->avatar_frame = (int)$frame->id;
        $user->save(false, ['avatar_frame']);

        return $this->successResponse([
            'avatar_frame_id' => (int)$frame->id,
            'avatar_frame_url' => $frame->getImageUrl(),
        ]);
    }
}

