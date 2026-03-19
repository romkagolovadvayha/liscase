<?php

namespace api\controllers\v1;

use Yii;
use DateTimeImmutable;
use DateTimeZone;
use common\models\servers\Servers;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с календарем вайпов
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="WipeCalendar")
 */
class WipeCalendarController extends BaseApiController
{
    /**
     * Получение календаря вайпов
     * 
     * @OA\Get(
     *     path="/v1/wipe-calendar",
     *     operationId="getWipeCalendar",
     *     tags={"WipeCalendar"},
     *     summary="Получить календарь вайпов",
     *     description="Возвращает календарь вайпов для указанного месяца и года",
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Год (например: 2024)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Месяц (1-12)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=12)
     *     ),
     *     @OA\Parameter(
     *         name="months",
     *         in="query",
     *         description="Количество месяцев для отображения",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Календарь вайпов (только даты с событиями)",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="events",
     *                     type="object",
     *                     description="Объект, где ключи - даты в формате Y-m-d, значения - массивы событий",
     *                     additionalProperties={
     *                         "type": "array",
     *                         "items": {
     *                             "type": "object",
     *                             "properties": {
     *                                 "name": {"type": "string"},
     *                                 "link": {"type": "string", "nullable": true},
     *                                 "time": {"type": "string"},
     *                                 "is_official": {"type": "boolean"},
     *                                 "is_global": {"type": "boolean"},
     *                                 "badges": {"type": "array"},
     *                                 "desc": {"type": "string", "nullable": true}
     *                             }
     *                         }
     *                     }
     *                 ),
     *                 @OA\Property(property="currentYear", type="integer"),
     *                 @OA\Property(property="currentMonth", type="integer"),
     *                 @OA\Property(property="shownMonths", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function actionIndex($year = null, $month = null, $months = 1)
    {
        if (!Yii::$app->settings->get('section_calendar')) {
            return $this->errorResponse('CALENDAR_DISABLED', 'Календарь вайпов отключен', [], 404);
        }

        $tz  = new DateTimeZone(Yii::$app->timeZone ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);

        $year   = $year   ? (int)$year  : (int)$now->format('Y');
        $month  = $month  ? (int)$month : (int)$now->format('n');
        $months = (int)max(1, (int)$months);

        // Кэш отключён (временно)
        // $cacheKey = 'api_wipe_calendar_' . $year . '_' . $month . '_' . $months;
        // $cache = Yii::$app->cache;
        // $cached = $cache->get($cacheKey);
        // if ($cached !== false) {
        //     return $this->successResponse($cached);
        // }

        // Время событий
        $globalTime = '21:00:00'; // глобал (четверг)
        $mapTime    = '16:00:00'; // карта (пятница)

        // Диапазон месяцев
        $firstMonthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $monthStarts = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStarts[] = $firstMonthStart->modify('+' . $i . ' month');
        }
        $afterLastMonth = $firstMonthStart->modify('+' . $months . ' month');

        // Активные сервера (wipe_weekday: 1=Пн..7=Вс, по умолчанию 5=Пятница)
        /** @var Servers[] $activeServers */
        $activeServers = Servers::find()
                                ->select(['id', 'name', 'monitoring_name', 'tag', 'wipe_type', 'wipe_weekday', 'status', 'sort'])
                                ->andWhere(['status' => Servers::STATUS_ACTIVE])
                                ->orderBy(['sort' => SORT_ASC])
                                ->all();

        // Серверы, участвующие в пятничных вайпах (исключаем тип 30)
        $non30Active = array_values(array_filter($activeServers, function ($s) {
            return (int)$s->wipe_type !== 30;
        }));
        $countNon30 = count($non30Active);

        // Обновление игры (четверг 21:00) — отдельно, не вайп. globalDates (первая пятница) — для 14-дневных. Все вайпы только в 16:00.
        $officialByDateTime = [];
        $globalDates       = [];
        foreach ($monthStarts as $mStart) {
            $firstThu = $this->firstWeekdayOfMonth($mStart, 4);
            $firstFri = $this->firstWeekdayOfMonth($mStart, 5);
            $officialDT = new DateTimeImmutable($firstThu->format('Y-m-d') . ' ' . $globalTime, $tz);
            $officialByDateTime[$officialDT->format('Y-m-d H:i:s')] = true;
            $globalDates[$firstFri->format('Y-m-d')] = true;
        }

        // === 2) Слоты по точному времени ===
        $byDateTime = [];

        // Официальное обновление игры (четверг 21:00) — не вайп, отдельное событие
        foreach ($officialByDateTime as $dtStr => $_) {
            $byDateTime[$dtStr] = [
                'official'         => true,
                'global'           => false,
                'servers'          => [],
                'weekly7_count'    => 0,
                'biweekly14_count' => 0,
                'names7'           => [],
                'names14'          => [],
                'title'            => Yii::t('common', 'Обновление игры'),
                'link'             => null,
                'names'            => [],
            ];
        }

        // 7-дневные: чередование по неделям месяца — нед.1 глобал, нед.2 вайп карты, нед.3 глобал, нед.4 вайп карты.
        foreach ($non30Active as $s) {
            if ((int)$s->wipe_type === 7) {
                $this->addWeeklyMapWipes($byDateTime, $monthStarts, $afterLastMonth, $mapTime, $s, $tz);
            }
        }

        // 14-дневные: глобал → через 14 дней вайп карты (wipe_weekday) → через 14 дней снова глобал. Неделю глобала не блокируем.
        foreach ($non30Active as $s) {
            if ((int)$s->wipe_type === 14) {
                $this->addBiweeklyMapWipes($byDateTime, $monthStarts, $afterLastMonth, $globalDates, $globalTime, $mapTime, $s, $tz);
            }
        }

        // === 5) Агрегация ===
        foreach ($byDateTime as $dtStr => &$bucket) {
            if (!empty($bucket['official']) || !empty($bucket['global'])) {
                continue;
            }
            $hasServers = !empty($bucket['servers']);
            $count7  = isset($bucket['weekly7_count']) ? (int)$bucket['weekly7_count'] : 0;
            $count14 = isset($bucket['biweekly14_count']) ? (int)$bucket['biweekly14_count'] : 0;

            // A) В моменте есть 7- и 14-дневные и вместе покрывают всех non-30 → «Вайп»
            if ($hasServers && $countNon30 > 0 && ($count7 + $count14) === $countNon30 && $count7 > 0 && $count14 > 0) {
                $bucket['title']   = Yii::t('common', 'Вайп');
                $bucket['link']    = '/servers';
                $bucket['names7']  = array_values(array_unique($bucket['names7']));
                $bucket['names14'] = array_values(array_unique($bucket['names14']));
                $bucket['names']   = [];
                $bucket['servers'] = [];
                continue;
            }

            // B) Только 7-дневные (>=2) → «Вайп карты»
            if ($hasServers && $count7 >= 2 && $count14 === 0) {
                $bucket['title']   = Yii::t('common', 'Вайп карты');
                $bucket['link']    = '/servers';
                $bucket['names']   = array_values(array_unique($bucket['names7']));
                $bucket['servers'] = [];
                continue;
            }

            // C) Совпало у всех non-30 (только один тип) → «Вайп»
            if ($hasServers && ($count7 + $count14) === $countNon30 && $countNon30 > 0) {
                $bucket['title']   = Yii::t('common', 'Вайп');
                $bucket['link']    = '/servers';
                $bucket['names']   = [Yii::t('common', 'все сервера')];
                $bucket['servers'] = [];
            }
        }
        unset($bucket);

        // === 6) Events для API ===
        $events = [];
        foreach ($byDateTime as $dtStr => $bucket) {
            $dt      = new DateTimeImmutable($dtStr, $tz);
            $dayKey  = $dt->format('Y-m-d');
            $timeTxt = $dt->format('H:i');

            $badges = [];

            if (!empty($bucket['official'])) {
                $events[$dayKey][] = [
                    'name'        => $bucket['title'],
                    'link'        => $bucket['link'],
                    'time'        => $timeTxt,
                    'is_official' => true,
                    'is_global'   => false,
                    'badges'      => [],
                    'desc'        => null,
                ];
                continue;
            }

            if (!empty($bucket['global'])) {
                if (!empty($bucket['names7'])) {
                    $badges[] = ['class' => 'badge-global', 'text' => Yii::t('common', '{list}', ['list' => implode(', ', array_unique($bucket['names7']))])];
                } else {
                    $badges[] = ['class' => 'badge-global', 'text' => Yii::t('common', 'все сервера')];
                }
                $events[$dayKey][] = [
                    'name'        => $bucket['title'],
                    'link'        => $bucket['link'],
                    'time'        => $timeTxt,
                    'is_official' => false,
                    'is_global'   => true,
                    'badges'      => $badges,
                    'desc'        => null,
                ];
                if (empty($bucket['servers'])) {
                    continue;
                }
            }

            // Схлопнутые
            if (isset($bucket['title']) && empty($bucket['servers'])) {
                if (!empty($bucket['names7']) || !empty($bucket['names14'])) {
                    if (!empty($bucket['names7'])) {
                        $badges[] = ['class' => 'badge-map-wipe', 'text' => Yii::t('common', '{list}',  ['list' => implode(', ', $bucket['names7'])])];
                    }
                    if (!empty($bucket['names14'])) {
                        $badges[] = ['class' => 'badge-map-wipe', 'text' => Yii::t('common', '{list}', ['list' => implode(', ', $bucket['names14'])])];
                    }
                } elseif (!empty($bucket['names'])) {
                    $text = ($bucket['names'][0] === Yii::t('common', 'все сервера'))
                        ? Yii::t('common', 'все сервера')
                        : implode(', ', $bucket['names']);
                    $badges[] = ['class' => ($text === Yii::t('common', 'все сервера') ? 'badge-global' : 'badge-map-wipe'), 'text' => $text];
                }

                $events[$dayKey][] = [
                    'name'        => $bucket['title'],
                    'link'        => isset($bucket['link']) ? $bucket['link'] : null,
                    'time'        => $timeTxt,
                    'is_official' => false,
                    'is_global'   => false,
                    'badges'      => $badges,
                    'desc'        => null,
                ];
                continue;
            }

            // По серверам отдельно
            if (!empty($bucket['servers'])) {
                foreach ($bucket['servers'] as $srv) {
                    $badges = [
                        ['class' => 'badge-map-wipe',
                         'text'  => $srv['monitoring_name'] ?: $srv['name']],
                    ];
                    $events[$dayKey][] = [
                        'name'        => Yii::t('common', 'Вайп карты — {server}', ['server' => $srv['name']]),
                        'link'        => $srv['link'],
                        'time'        => $timeTxt,
                        'is_official' => false,
                        'is_global'   => false,
                        'badges'      => $badges,
                        'desc'        => null,
                    ];
                }
            }
        }

        // === 7) Формируем ответ только с датами, где есть события ===
        $eventsByDate = [];
        foreach ($events as $date => $dateEvents) {
            if (!empty($dateEvents)) {
                $eventsByDate[$date] = $dateEvents;
            }
        }

        return $this->successResponse([
            'events' => $eventsByDate,
            'currentYear'  => $year,
            'currentMonth' => $month,
            'shownMonths'  => $months,
        ]);
    }

    /**
     * Получение календаря вайпов для конкретного сервера
     * 
     * @OA\Get(
     *     path="/v1/wipe-calendar/server",
     *     operationId="getServerWipeCalendar",
     *     tags={"WipeCalendar"},
     *     summary="Получить календарь вайпов для конкретного сервера",
     *     description="Возвращает календарь вайпов для указанного сервера на указанный месяц и год",
     *     @OA\Parameter(
     *         name="server_id",
     *         in="query",
     *         required=true,
     *         description="ID сервера",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Год (например: 2024)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Месяц (1-12)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=12)
     *     ),
     *     @OA\Parameter(
     *         name="months",
     *         in="query",
     *         description="Количество месяцев для отображения",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Календарь вайпов сервера (только даты с событиями)",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="events",
     *                     type="object",
     *                     description="Объект, где ключи - даты в формате Y-m-d, значения - массивы событий",
     *                     additionalProperties={
     *                         "type": "array",
     *                         "items": {
     *                             "type": "object",
     *                             "properties": {
     *                                 "name": {"type": "string"},
     *                                 "link": {"type": "string", "nullable": true},
     *                                 "time": {"type": "string"},
     *                                 "is_official": {"type": "boolean"},
     *                                 "is_global": {"type": "boolean"},
     *                                 "badges": {"type": "array"},
     *                                 "desc": {"type": "string", "nullable": true}
     *                             }
     *                         }
     *                     }
     *                 ),
     *                 @OA\Property(property="currentYear", type="integer"),
     *                 @OA\Property(property="currentMonth", type="integer"),
     *                 @OA\Property(property="shownMonths", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function actionServer($server_id, $year = null, $month = null, $months = 1)
    {
        if (!Yii::$app->settings->get('section_calendar')) {
            return $this->errorResponse('CALENDAR_DISABLED', 'Календарь вайпов отключен', [], 404);
        }

        $server = Servers::findOne($server_id);
        if (!$server) {
            return $this->errorResponse('SERVER_NOT_FOUND', 'Сервер не найден', [], 404);
        }

        $tz  = new DateTimeZone(Yii::$app->timeZone ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);

        $year   = $year   ? (int)$year  : (int)$now->format('Y');
        $month  = $month  ? (int)$month : (int)$now->format('n');
        $months = (int)max(1, (int)$months);

        // Время событий
        $globalTime = '21:00:00';
        $mapTime    = '16:00:00';

        // Диапазон месяцев
        $firstMonthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $monthStarts = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStarts[] = $firstMonthStart->modify('+' . $i . ' month');
        }
        $afterLastMonth = $firstMonthStart->modify('+' . $months . ' month');

        $officialByDateTime = [];
        $globalDates       = [];
        foreach ($monthStarts as $mStart) {
            $firstThu = $this->firstWeekdayOfMonth($mStart, 4);
            $firstFri = $this->firstWeekdayOfMonth($mStart, 5);
            $officialDT = new DateTimeImmutable($firstThu->format('Y-m-d') . ' ' . $globalTime, $tz);
            $officialByDateTime[$officialDT->format('Y-m-d H:i:s')] = true;
            $globalDates[$firstFri->format('Y-m-d')] = true;
        }

        // === 2) Слоты по точному времени ===
        $byDateTime = [];

        // Официальное обновление игры (четверг 21:00) — не вайп
        foreach ($officialByDateTime as $dtStr => $_) {
            $byDateTime[$dtStr] = [
                'official' => true,
                'global'   => false,
                'servers'  => [],
                'title'    => Yii::t('common', 'Обновление игры'),
                'link'     => null,
            ];
        }

        // Вайпы по дню недели сервера (wipe_weekday), время всегда 16:00
        $serverWipeWeekday = (int)($server->wipe_weekday ?? 5);
        if ($serverWipeWeekday < 1 || $serverWipeWeekday > 7) {
            $serverWipeWeekday = 5;
        }
        if ((int)$server->wipe_type !== 30) {
            $wt = (int)$server->wipe_type;
            if ($wt === 7) {
                $this->addWeeklyMapWipes($byDateTime, $monthStarts, $afterLastMonth, $mapTime, $server, $tz);
            } elseif ($wt === 14) {
                $this->addBiweeklyMapWipes($byDateTime, $monthStarts, $afterLastMonth, $globalDates, $globalTime, $mapTime, $server, $tz);
            }
        }

        // === 3) Events для API ===
        $events = [];
        foreach ($byDateTime as $dtStr => $bucket) {
            $dt      = new DateTimeImmutable($dtStr, $tz);
            $dayKey  = $dt->format('Y-m-d');
            $timeTxt = $dt->format('H:i');

            $badges = [];

            if (!empty($bucket['official'])) {
                $events[$dayKey][] = [
                    'name'        => $bucket['title'],
                    'link'        => $bucket['link'],
                    'time'        => $timeTxt,
                    'is_official' => true,
                    'is_global'   => false,
                    'badges'      => [],
                    'desc'        => null,
                ];
                continue;
            }

            if (!empty($bucket['global'])) {
                if (!empty($bucket['names7'])) {
                    $badges[] = ['class' => 'badge-global', 'text' => Yii::t('common', '{list}', ['list' => implode(', ', array_unique($bucket['names7']))])];
                } else {
                    $badges[] = ['class' => 'badge-global', 'text' => Yii::t('common', 'все сервера')];
                }
                $events[$dayKey][] = [
                    'name'        => $bucket['title'],
                    'link'        => $bucket['link'],
                    'time'        => $timeTxt,
                    'is_official' => false,
                    'is_global'   => true,
                    'badges'      => $badges,
                    'desc'        => null,
                ];
                if (empty($bucket['servers'])) {
                    continue;
                }
            }

            // По серверам отдельно
            if (!empty($bucket['servers'])) {
                foreach ($bucket['servers'] as $srv) {
                    if ($srv['id'] == $server->id) {
                        $badges = [
                            ['class' => 'badge-map-wipe',
                             'text'  => $srv['monitoring_name'] ?: $srv['name']],
                        ];
                        $events[$dayKey][] = [
                            'name'        => Yii::t('common', 'Вайп карты — {server}', ['server' => $srv['name']]),
                            'link'        => $srv['link'],
                            'time'        => $timeTxt,
                            'is_official' => false,
                            'is_global'   => false,
                            'badges'      => $badges,
                            'desc'        => null,
                        ];
                    }
                }
            }
        }

        // === 4) Формируем ответ только с датами, где есть события ===
        $eventsByDate = [];
        foreach ($events as $date => $dateEvents) {
            if (!empty($dateEvents)) {
                $eventsByDate[$date] = $dateEvents;
            }
        }

        return $this->successResponse([
            'events' => $eventsByDate,
            'currentYear'  => $year,
            'currentMonth' => $month,
            'shownMonths'  => $months,
        ]);
    }

    /**
     * wipe_type=7: чередование по неделям месяца — нед.1 глобал, нед.2 вайп карты, нед.3 глобал, нед.4 вайп карты и т.д.
     * Глобал только для серверов с вайпом в этот день недели (бейджи — список этих серверов, не «все сервера»).
     */
    private function addWeeklyMapWipes(array &$byDateTime, array $monthStarts, DateTimeImmutable $afterLastMonth, $mapTime, Servers $s, DateTimeZone $tz)
    {
        $weekday = (int)($s->wipe_weekday ?? 5);
        if ($weekday < 1 || $weekday > 7) {
            $weekday = 5;
        }
        $emptyBucket = [
            'global' => false, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
            'names7' => [], 'names14' => [], 'title' => null, 'link' => null, 'names' => [],
        ];
        foreach ($monthStarts as $mStart) {
            $dt = $this->firstWeekdayOfMonth($mStart, $weekday);
            $dt = new DateTimeImmutable($dt->format('Y-m-d') . ' ' . $mapTime, $tz);
            while ($dt < $afterLastMonth) {
                $key = $dt->format('Y-m-d H:i:s');
                $dayOfMonth = (int)$dt->format('j');
                $weekOfMonth = (int)floor(($dayOfMonth - 1) / 7) + 1;
                $isGlobalSlot = ($weekOfMonth % 2 === 1);
                if ($isGlobalSlot) {
                    if (!isset($byDateTime[$key])) {
                        $byDateTime[$key] = [
                            'global' => true, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
                            'names7' => [], 'names14' => [], 'title' => Yii::t('common', 'Глобальный вайп'), 'link' => '/servers', 'names' => [],
                        ];
                    }
                    $byDateTime[$key]['names7'][] = $s->monitoring_name ?: $s->name;
                } else {
                    if (!isset($byDateTime[$key])) {
                        $byDateTime[$key] = $emptyBucket;
                    }
                    $byDateTime[$key]['servers'][$s->id] = [
                        'id' => (int)$s->id, 'name' => $s->name, 'monitoring_name' => $s->monitoring_name,
                        'link' => $s->getLink('stats'), 'text_ip' => $s->text_ip, 'wt' => 7,
                    ];
                    $byDateTime[$key]['weekly7_count']++;
                    $byDateTime[$key]['names7'][] = $s->monitoring_name ?: $s->name;
                }
                $dt = $dt->modify('+7 days');
            }
        }
    }

    /**
     * wipe_type=14: в первую неделю в wipe_weekday — глобал, через 14 дней — вайп карты, через 14 дней — снова глобал (след. месяц).
     * Слоты: первый wipe_weekday месяца + 14 дней, +28, +42… Не блокируем неделю глобала; не ставим слот только в дату глобала (первая пятница).
     */
    private function addBiweeklyMapWipes(array &$byDateTime, array $monthStarts, DateTimeImmutable $afterLastMonth, array $globalDates, $globalTime, $mapTime, Servers $s, DateTimeZone $tz)
    {
        $weekday = (int)($s->wipe_weekday ?? 5);
        if ($weekday < 1 || $weekday > 7) {
            $weekday = 5;
        }
        $emptyBucket = [
            'global' => false, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
            'names7' => [], 'names14' => [], 'title' => null, 'link' => null, 'names' => [],
        ];
        foreach ($monthStarts as $mStart) {
            $firstDay = $this->firstWeekdayOfMonth($mStart, $weekday);
            $dt = new DateTimeImmutable($firstDay->format('Y-m-d') . ' ' . $mapTime, $tz);
            $dt = $dt->modify('+14 days');
            while ($dt < $afterLastMonth) {
                if (!isset($globalDates[$dt->format('Y-m-d')])) {
                    $key = $dt->format('Y-m-d H:i:s');
                    if (!isset($byDateTime[$key])) {
                        $byDateTime[$key] = $emptyBucket;
                    }
                    $byDateTime[$key]['servers'][$s->id] = [
                        'id' => (int)$s->id, 'name' => $s->name, 'monitoring_name' => $s->monitoring_name,
                        'link' => $s->getLink('stats'), 'text_ip' => $s->text_ip, 'wt' => 14,
                    ];
                    $byDateTime[$key]['biweekly14_count']++;
                    $byDateTime[$key]['names14'][] = $s->monitoring_name ?: $s->name;
                }
                $dt = $dt->modify('+14 days');
            }
        }
    }

    /** Первый N-й день недели месяца: $weekday 1=Пн..7=Вс */
    private function firstWeekdayOfMonth(DateTimeImmutable $monthStart, $weekday)
    {
        $firstN = (int)$monthStart->format('N');
        $delta  = (int)$weekday - $firstN;
        if ($delta < 0) $delta += 7;
        return $monthStart->modify('+' . $delta . ' day');
    }

    /** Ближайшая дата на или после $date с днём недели $weekday (1–7), время $mapTime */
    private function nextWeekdayOnOrAfter(DateTimeImmutable $date, $weekday, $mapTime, DateTimeZone $tz)
    {
        $currentN = (int)$date->format('N');
        $delta = (int)$weekday - $currentN;
        if ($delta < 0) {
            $delta += 7;
        }
        $targetDate = $date->modify('+' . $delta . ' day');
        return new DateTimeImmutable($targetDate->format('Y-m-d') . ' ' . $mapTime, $tz);
    }

    /** Сетка месяца с серыми соседними днями */
    private function buildMonthGrid(DateTimeImmutable $monthStart, array $eventsByDay, DateTimeZone $tz)
    {
        $year   = (int)$monthStart->format('Y');
        $month  = (int)$monthStart->format('n');
        $daysIn = (int)$monthStart->format('t');
        $firstN = (int)$monthStart->format('N'); // 1..7
        $lead   = $firstN - 1;

        $monthsRu = [
            1  => Yii::t('common', 'Январь'),
            2  => Yii::t('common', 'Февраль'),
            3  => Yii::t('common', 'Март'),
            4  => Yii::t('common', 'Апрель'),
            5  => Yii::t('common', 'Май'),
            6  => Yii::t('common', 'Июнь'),
            7  => Yii::t('common', 'Июль'),
            8  => Yii::t('common', 'Август'),
            9  => Yii::t('common', 'Сентябрь'),
            10 => Yii::t('common', 'Октябрь'),
            11 => Yii::t('common', 'Ноябрь'),
            12 => Yii::t('common', 'Декабрь'),
        ];
        $title = $monthsRu[$month] . ' ' . $year;

        $todayKey = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

        $cells = [];

        // Лидирующие (предыдущий месяц)
        if ($lead > 0) {
            $prevMonth = $monthStart->modify('-1 month');
            $prevYear  = (int)$prevMonth->format('Y');
            $prevNum   = (int)$prevMonth->format('n');
            $prevDays  = (int)$prevMonth->format('t');
            for ($i = $lead - 1; $i >= 0; $i--) {
                $d = $prevDays - $i;
                $dateKey = sprintf('%04d-%02d-%02d', $prevYear, $prevNum, $d);
                $cells[] = [
                    'empty'        => false,
                    'day'          => $d,
                    'date'         => $dateKey,
                    'events'       => [],
                    'isToday'      => ($dateKey === $todayKey),
                    'isOtherMonth' => true,
                ];
            }
        }

        // Текущий месяц
        for ($day = 1; $day <= $daysIn; $day++) {
            $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $cells[] = [
                'empty'        => false,
                'day'          => $day,
                'date'         => $dateKey,
                'events'       => isset($eventsByDay[$dateKey]) ? $eventsByDay[$dateKey] : [],
                'isToday'      => ($dateKey === $todayKey),
                'isOtherMonth' => false,
            ];
        }

        // Хвост (следующий месяц)
        $mod = count($cells) % 7;
        $tail = ($mod === 0) ? 0 : (7 - $mod);
        if ($tail > 0) {
            $nextMonth = $monthStart->modify('+1 month');
            $nextYear  = (int)$nextMonth->format('Y');
            $nextNum   = (int)$nextMonth->format('n');
            for ($d = 1; $d <= $tail; $d++) {
                $dateKey = sprintf('%04d-%02d-%02d', $nextYear, $nextNum, $d);
                $cells[] = [
                    'empty'        => false,
                    'day'          => $d,
                    'date'         => $dateKey,
                    'events'       => [],
                    'isToday'      => ($dateKey === $todayKey),
                    'isOtherMonth' => true,
                ];
            }
        }

        $weeks = array_chunk($cells, 7);

        $prev = $monthStart->modify('-1 month');
        $next = $monthStart->modify('+1 month');

        $result = [
            'title'   => $title,
            'year'    => $year,
            'month'   => $month,
            'weeks'   => $weeks,
            'prevUrl' => [
                'year' => (int)$prev->format('Y'),
                'month' => (int)$prev->format('n')
            ],
            'nextUrl' => [
                'year' => (int)$next->format('Y'),
                'month' => (int)$next->format('n')
            ],
        ];

        // Кэш отключён: $cache->set($cacheKey, $result, 3600);

        return $this->successResponse($result);
    }
}

