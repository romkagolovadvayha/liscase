<?php

namespace api\controllers\v1;

use common\components\calendar\RfCalendarHighlightHelper;
use common\components\servers\ServerDisplayNameParts;
use common\helpers\ApiPublicCacheTtl;
use common\models\servers\Servers;
use common\models\wipe_calendar\WipeCalendarEvent;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Публичный календарь вайпов для сайта (Next.js).
 *
 * @OA\Tag(name="WipeCalendar")
 */
class WipeCalendarController extends BaseApiController
{
    private const COLOR_MAP = [
        WipeCalendarEvent::TYPE_MAP_WIPE => '#0d6efd',
        WipeCalendarEvent::TYPE_GLOBAL_WIPE => '#dc3545',
        WipeCalendarEvent::TYPE_GAME_UPDATE => '#fd7e14',
        WipeCalendarEvent::TYPE_CUSTOM => '#6f42c1',
    ];

    /**
     * События и подсветка дней РФ за период.
     *
     * @OA\Get(
     *     path="/v1/wipe-calendar",
     *     operationId="getWipeCalendar",
     *     tags={"WipeCalendar"},
     *     summary="Календарь вайпов и событий",
     *     @OA\Parameter(name="start", in="query", required=true, @OA\Schema(type="string", example="2026-05-01")),
     *     @OA\Parameter(name="end", in="query", required=true, @OA\Schema(type="string", example="2026-05-31")),
     *     @OA\Parameter(name="highlights", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function actionIndex()
    {
        $req = Yii::$app->request;
        $start = (string) $req->get('start', '');
        $end = (string) $req->get('end', '');
        if (strlen($start) < 10 || strlen($end) < 10) {
            throw new BadRequestHttpException('Query parameters start and end are required (Y-m-d).');
        }
        $startDay = substr($start, 0, 10);
        $endDay = substr($end, 0, 10);

        $withHighlights = (string) $req->get('highlights', '1') !== '0';

        $cacheKey = 'api_wipe_calendar_' . md5($startDay . '|' . $endDay . '|' . ($withHighlights ? '1' : '0') . '|' . Yii::$app->language);
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return $this->successResponse($cached);
        }

        $models = WipeCalendarEvent::find()
            ->with('server')
            ->andWhere(['>=', 'event_at', $startDay . ' 00:00:00'])
            ->andWhere(['<=', 'event_at', $endDay . ' 23:59:59'])
            ->orderBy(['event_at' => SORT_ASC])
            ->all();

        $typeLabels = WipeCalendarEvent::typeList();
        $events = [];
        foreach ($models as $m) {
            $events[] = self::serializeEvent($m, $typeLabels);
        }

        $payload = [
            'events' => $events,
        ];
        if ($withHighlights) {
            $payload['highlights'] = RfCalendarHighlightHelper::highlightsBetween($startDay, $endDay);
        }

        Yii::$app->cache->set($cacheKey, $payload, ApiPublicCacheTtl::SECONDS);

        return $this->successResponse($payload);
    }

    /**
     * @param array<string, string> $typeLabels
     */
    private static function serializeEvent(WipeCalendarEvent $m, array $typeLabels): array
    {
        $t = $m->event_type;
        $color = self::COLOR_MAP[$t] ?? '#6c757d';
        $title = $m->getCalendarTitle();

        $serverPayload = null;
        if ($m->server instanceof Servers) {
            $parts = ServerDisplayNameParts::fromServer($m->server);
            $serverPayload = [
                'id' => (int) $m->server->id,
                'index' => $parts['index'],
                'index_with_hash' => $parts['index_with_hash'],
                'short_name' => $parts['short_name'],
                'tag' => $parts['tag'],
            ];
        }

        return [
            'id' => (int) $m->id,
            'event_type' => $t,
            'event_type_label' => $typeLabels[$t] ?? $t,
            'title' => $m->title,
            'calendar_title' => $title,
            'event_at' => $m->event_at,
            'start' => str_replace(' ', 'T', $m->event_at),
            'color' => $color,
            'server_id' => $m->server_id !== null ? (int) $m->server_id : null,
            'server' => $serverPayload,
        ];
    }
}
