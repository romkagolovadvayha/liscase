<?php

namespace api\controllers\v1;

use common\components\calendar\RfCalendarHighlightHelper;
use common\components\servers\ServerDisplayNameParts;
use common\helpers\ApiPublicCacheTtl;
use common\models\servers\Servers;
use common\models\wipe_calendar\WipeCalendarEvent;
use DateTimeImmutable;
use DateTimeZone;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

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
     * События календаря для виджета на странице сервера (по дням), из таблицы {@see WipeCalendarEvent::tableName()}.
     *
     * Только события, относящиеся к этому серверу: {@see WipeCalendarEvent::server_id} = запрошенному id
     * либо общее «Обновление игры» без привязки к серверу (`server_id` IS NULL и тип {@see WipeCalendarEvent::TYPE_GAME_UPDATE}).
     * Плюс подсветка выходных и праздников РФ за тот же период ({@see RfCalendarHighlightHelper::highlightsBetween}).
     *
     * @OA\Get(
     *     path="/v1/wipe-calendar/server",
     *     operationId="getWipeCalendarServer",
     *     tags={"WipeCalendar"},
     *     summary="Календарь вайпов по одному серверу",
     *     @OA\Parameter(name="server_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", required=true, @OA\Schema(type="integer", example=2026)),
     *     @OA\Parameter(name="month", in="query", required=true, @OA\Schema(type="integer", example=4)),
     *     @OA\Parameter(name="months", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function actionServer()
    {
        $req = Yii::$app->request;
        $serverId = (int) $req->get('server_id', 0);
        $year = (int) $req->get('year', 0);
        $month = (int) $req->get('month', 0);
        $months = (int) $req->get('months', 1);

        if ($serverId <= 0 || $year < 1970 || $month < 1 || $month > 12) {
            throw new BadRequestHttpException('Query parameters server_id, year (>= 1970), and month (1–12) are required.');
        }
        $months = max(1, min(24, $months));

        $server = Servers::findOne($serverId);
        if ($server === null) {
            throw new NotFoundHttpException('Server not found.');
        }

        $tz = new DateTimeZone(Yii::$app->timeZone ?: 'Europe/Moscow');
        $dtStart = DateTimeImmutable::createFromFormat('Y-n-j', $year . '-' . $month . '-1', $tz);
        if ($dtStart === false) {
            throw new BadRequestHttpException('Invalid year/month.');
        }
        $dtEndMonth = $dtStart->modify('+' . ($months - 1) . ' months');
        $lastDay = (int) $dtEndMonth->format('t');
        $dtEnd = $dtEndMonth->setDate(
            (int) $dtEndMonth->format('Y'),
            (int) $dtEndMonth->format('n'),
            $lastDay
        )->setTime(23, 59, 59);

        $startStr = $dtStart->format('Y-m-d') . ' 00:00:00';
        $endStr = $dtEnd->format('Y-m-d H:i:s');

        $cacheKey = 'api_wipe_calendar_server_' . md5(
            (string) $serverId . '|' . $startStr . '|' . $endStr . '|' . Yii::$app->language . '|v3et'
        );
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return $this->successResponse($cached);
        }

        $models = WipeCalendarEvent::find()
            ->with('server')
            ->andWhere(['>=', 'event_at', $startStr])
            ->andWhere(['<=', 'event_at', $endStr])
            ->andWhere([
                'or',
                ['server_id' => $serverId],
                [
                    'and',
                    ['server_id' => null],
                    ['event_type' => WipeCalendarEvent::TYPE_GAME_UPDATE],
                ],
            ])
            ->orderBy(['event_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        /** @var array<string, list<array{id: int, date: string, event_type: string, is_global: bool, server_id?: int}>> $byDay */
        $byDay = [];
        foreach ($models as $m) {
            $day = substr((string) $m->event_at, 0, 10);
            if (!isset($byDay[$day])) {
                $byDay[$day] = [];
            }
            $byDay[$day][] = self::serializeServerWidgetEvent($m);
        }

        $startDay = $dtStart->format('Y-m-d');
        $endDay = $dtEnd->format('Y-m-d');

        $payload = [
            'events' => $byDay,
            'highlights' => RfCalendarHighlightHelper::highlightsBetween($startDay, $endDay),
        ];

        Yii::$app->cache->set($cacheKey, $payload, ApiPublicCacheTtl::SECONDS);

        return $this->successResponse($payload);
    }

    /**
     * @return array{id: int, date: string, event_type: string, is_global: bool, server_id?: int}
     */
    private static function serializeServerWidgetEvent(WipeCalendarEvent $m): array
    {
        $day = substr((string) $m->event_at, 0, 10);
        $t = $m->event_type;
        $row = [
            'id' => (int) $m->id,
            'date' => $day,
            'event_type' => $t,
            /** Только глобальный вайп; «обновление игры» — отдельный тип и стиль на фронте */
            'is_global' => $t === WipeCalendarEvent::TYPE_GLOBAL_WIPE,
        ];
        if ($m->server_id !== null) {
            $row['server_id'] = (int) $m->server_id;
        }

        return $row;
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
