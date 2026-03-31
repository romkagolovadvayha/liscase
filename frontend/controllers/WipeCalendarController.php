<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use DateTimeImmutable;
use DateTimeZone;
use common\models\servers\Servers;
use yii\web\NotFoundHttpException;

/**
 * Расчёт слотов и сырых событий — как {@see \api\controllers\v1\WipeCalendarController::actionIndex}.
 * Перед выводом: mergeDayEvents как в prostoj-frontend `WipeCalendarClient.tsx` (одна карточка «Вайп» на время).
 */
class WipeCalendarController extends Controller
{
    public function actionIndex($year = null, $month = null, $months = 1)
    {
        if (!Yii::$app->settings->get('section_calendar')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $this->view->params['page'] = 'wipe-calendar';
        $tz  = new DateTimeZone(Yii::$app->timeZone ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);

        $year   = $year   ? (int)$year  : (int)$now->format('Y');
        $month  = $month  ? (int)$month : (int)$now->format('n');
        $months = (int)max(1, (int)$months);

        // === SEO ===
        $title = Yii::t('common', 'Календарь вайпов Rust — официальный и серверные вайпы');
        $desc  = Yii::t('common', 'Календарь вайпов Rust: обновление игры — первый четверг месяца в 21:00; вайпы карт в 16:00 по циклам 7 и 14 дней и дню недели сервера. Глобальные слоты и вайп карты — как на сайте prostoj.store.');
        $keys  = Yii::t('common', 'календарь вайпов rust, rust вайп, глобальный вайп, 7-дневные, 14-дневные, расписание вайпов rust, обновление игры rust');

        $this->view->title = $title;
        $this->view->registerMetaTag(['name' => 'description', 'content' => $desc]);
        $this->view->registerMetaTag(['name' => 'keywords', 'content' => $keys]);
        $this->view->registerMetaTag(['property' => 'og:title', 'content' => $title]);
        $this->view->registerMetaTag(['property' => 'og:description', 'content' => $desc]);
        $this->view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary']);

        // Время событий (как в API v1)
        $globalTime = '21:00:00'; // глобал 14-дневных / слот «обновление» четверг
        $mapTime    = '16:00:00'; // вайп карты

        $firstMonthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $monthStarts = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStarts[] = $firstMonthStart->modify('+' . $i . ' month');
        }
        $afterLastMonth = $firstMonthStart->modify('+' . $months . ' month');

        /** @var Servers[] $activeServers */
        $activeServers = Servers::find()
            ->select(['id', 'name', 'monitoring_name', 'tag', 'wipe_type', 'wipe_weekday', 'status', 'sort', 'text_ip'])
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        // Как API /v1/wipe-calendar: в расчёт слотов не входят 30-дневные (месячные) — только 7 и 14.
        $non30Active = array_values(array_filter($activeServers, function ($s) {
            return (int)$s->wipe_type !== 30;
        }));
        $countNon30 = count($non30Active);

        $officialByDateTime = [];
        $globalDates       = [];
        foreach ($monthStarts as $mStart) {
            $firstThu = $this->firstWeekdayOfMonth($mStart, 4);
            $firstFri = $this->firstWeekdayOfMonth($mStart, 5);
            $officialDT = new DateTimeImmutable($firstThu->format('Y-m-d') . ' ' . $globalTime, $tz);
            $officialByDateTime[$officialDT->format('Y-m-d H:i:s')] = true;
            $globalDates[$firstFri->format('Y-m-d')] = true;
        }

        $byDateTime = [];

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

        foreach ($non30Active as $s) {
            if ((int)$s->wipe_type === 7) {
                if (strtolower((string)($s->tag ?? '')) === 'monday') {
                    continue;
                }
                $this->addWeeklyMapWipes($byDateTime, $monthStarts, $afterLastMonth, $mapTime, $s, $tz);
            }
        }

        $mondayServers = Servers::find()
            ->select(['id', 'name', 'monitoring_name', 'tag', 'wipe_type', 'wipe_weekday', 'status', 'sort', 'text_ip'])
            ->andWhere(['tag' => 'monday'])
            ->orderBy(['sort' => SORT_ASC])
            ->all();
        foreach ($mondayServers as $s) {
            $this->addMondaysWeekly($byDateTime, $monthStarts, $afterLastMonth, $mapTime, $s, $tz);
        }

        foreach ($non30Active as $s) {
            if ((int)$s->wipe_type === 14) {
                $this->addBiweeklyMapWipes($byDateTime, $monthStarts, $afterLastMonth, $globalDates, $globalTime, $mapTime, $s, $tz);
            }
        }

        // === Агрегация (как API === 5)) ===
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

        $serverLinkByDisplayName = [];
        foreach ($activeServers as $s) {
            $displayName = trim((string)($s->monitoring_name ?: $s->name));
            if ($displayName !== '') {
                $serverLinkByDisplayName[$displayName] = $s->getLink('stats');
            }
        }
        foreach ($mondayServers as $s) {
            $displayName = trim((string)($s->monitoring_name ?: $s->name));
            if ($displayName !== '' && empty($serverLinkByDisplayName[$displayName])) {
                $serverLinkByDisplayName[$displayName] = $s->getLink('stats');
            }
        }

        // === Events для страницы (как API === 6)) ===
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
                    foreach (array_unique($bucket['names7']) as $name) {
                        $key = trim((string)$name);
                        $badges[] = ['class' => 'badge-global', 'text' => $name, 'link' => ($key !== '' ? ($serverLinkByDisplayName[$key] ?? null) : null)];
                    }
                }
                if (!empty($bucket['names14'])) {
                    foreach (array_unique($bucket['names14']) as $name) {
                        $key = trim((string)$name);
                        $badges[] = ['class' => 'badge-global', 'text' => $name, 'link' => ($key !== '' ? ($serverLinkByDisplayName[$key] ?? null) : null)];
                    }
                }
                if (empty($badges)) {
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

            // Схлопнутые (агрегация): «Вайп» — бейджи серверов как глобальный слот (фиолетовый); «Вайп карты» — зелёный.
            if (isset($bucket['title']) && empty($bucket['servers'])) {
                $collapsedNameClass = ($bucket['title'] === Yii::t('common', 'Вайп')) ? 'badge-global' : 'badge-map-wipe';
                if (!empty($bucket['names7']) || !empty($bucket['names14'])) {
                    if (!empty($bucket['names7'])) {
                        foreach (array_unique($bucket['names7']) as $name) {
                            $key = trim((string)$name);
                            $badges[] = ['class' => $collapsedNameClass, 'text' => $name, 'link' => ($key !== '' ? ($serverLinkByDisplayName[$key] ?? null) : null)];
                        }
                    }
                    if (!empty($bucket['names14'])) {
                        foreach (array_unique($bucket['names14']) as $name) {
                            $key = trim((string)$name);
                            $badges[] = ['class' => $collapsedNameClass, 'text' => $name, 'link' => ($key !== '' ? ($serverLinkByDisplayName[$key] ?? null) : null)];
                        }
                    }
                } elseif (!empty($bucket['names'])) {
                    $allServersText = Yii::t('common', 'все сервера');
                    if ($bucket['names'][0] === $allServersText) {
                        $badges[] = ['class' => 'badge-global', 'text' => $allServersText];
                    } else {
                        foreach (array_unique($bucket['names']) as $name) {
                            $key = trim((string)$name);
                            $badges[] = ['class' => $collapsedNameClass, 'text' => $name, 'link' => ($key !== '' ? ($serverLinkByDisplayName[$key] ?? null) : null)];
                        }
                    }
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
                    $wt = isset($srv['wt']) ? (int)$srv['wt'] : 7;
                    $badgeClass = ($wt === 71) ? 'badge-weekly-monday' : 'badge-map-wipe';
                    $badges = [
                        ['class' => $badgeClass,
                         'text'  => $srv['monitoring_name'] ?: $srv['name'],
                         'link'  => $srv['link']],
                    ];
                    $events[$dayKey][] = [
                        'name'        => Yii::t('common', 'Вайп карты'),
                        'link'        => null,
                        'time'        => $timeTxt,
                        'is_official' => false,
                        'is_global'   => false,
                        'badges'      => $badges,
                        'desc'        => null,
                    ];
                }
            }
        }

        foreach ($events as $dateKey => $dayList) {
            if ($dayList !== [] && is_array($dayList)) {
                $events[$dateKey] = $this->mergeDayEventsForDisplay($dayList);
            }
        }

        $canonical = Yii::$app->params['homePage'] . '/wipe-calendar';
        $this->view->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);

        $monthsData = [];
        foreach ($monthStarts as $mStart) {
            $monthsData[] = $this->buildMonthGrid($mStart, $events, $tz);
        }

        $monthsRu = [
            1 => Yii::t('common', 'января'), 2 => Yii::t('common', 'февраля'), 3 => Yii::t('common', 'марта'),
            4 => Yii::t('common', 'апреля'), 5 => Yii::t('common', 'мая'), 6 => Yii::t('common', 'июня'),
            7 => Yii::t('common', 'июля'), 8 => Yii::t('common', 'августа'), 9 => Yii::t('common', 'сентября'),
            10 => Yii::t('common', 'октября'), 11 => Yii::t('common', 'ноября'), 12 => Yii::t('common', 'декабря'),
        ];
        $formatWipeRow = function ($dt, $bucket) use ($monthsRu, $tz) {
            $title = null;
            $link = null;
            $serverId = null;
            if (!empty($bucket['official'])) {
                $title = $bucket['title'];
                $link = $bucket['link'];
            } elseif (!empty($bucket['global'])) {
                $title = $bucket['title'];
                $link = $bucket['link'];
            } elseif (!empty($bucket['title'])) {
                $title = $bucket['title'];
                $link = isset($bucket['link']) ? $bucket['link'] : null;
            } elseif (!empty($bucket['servers'])) {
                $first = reset($bucket['servers']);
                $title = Yii::t('common', 'Вайп карты');
                $link = $first['link'];
                $serverId = (count($bucket['servers']) === 1 && isset($first['id'])) ? (int)$first['id'] : null;
            }
            if ($title === null) {
                return null;
            }
            $dayNum = (int)$dt->format('j');
            $monthKey = (int)$dt->format('n');
            $yearNum = (int)$dt->format('Y');
            $dateFormatted = $dayNum . ' ' . $monthsRu[$monthKey] . ' ' . $yearNum;
            return [
                'date'      => $dateFormatted,
                'time'      => $dt->format('H:i'),
                'name'      => $title,
                'link'      => $link,
                'server_id' => $serverId,
            ];
        };

        $nearestWipe = null;
        $nowTs = $now->getTimestamp();
        ksort($byDateTime);
        foreach ($byDateTime as $dtStr => $bucket) {
            $dt = new DateTimeImmutable($dtStr, $tz);
            if ($dt->getTimestamp() < $nowTs) {
                continue;
            }
            $nearestWipe = $formatWipeRow($dt, $bucket);
            if ($nearestWipe !== null) {
                $nearestWipe['is_today'] = ($dt->format('Y-m-d') === $now->format('Y-m-d'));
                break;
            }
        }

        $recentWipe = null;
        krsort($byDateTime);
        foreach ($byDateTime as $dtStr => $bucket) {
            $dt = new DateTimeImmutable($dtStr, $tz);
            if ($dt->getTimestamp() >= $nowTs) {
                continue;
            }
            $recentWipe = $formatWipeRow($dt, $bucket);
            if ($recentWipe !== null) {
                $recentWipe['is_today'] = ($dt->format('Y-m-d') === $now->format('Y-m-d'));
                break;
            }
        }

        $serverForButtons = null;
        if (!empty($nearestWipe['server_id'])) {
            $serverForButtons = Servers::find()
                ->select(['id', 'ip', 'port', 'status'])
                ->andWhere(['id' => $nearestWipe['server_id']])
                ->one();
        }
        if ($serverForButtons && $serverForButtons->ip && $serverForButtons->port) {
            $nearestWipe['ip'] = $serverForButtons->ip;
            $nearestWipe['port'] = (int)$serverForButtons->port;
            $nearestWipe['connect_href'] = 'steam://rungameid/252490//+connect ' . $serverForButtons->ip . ':' . $serverForButtons->port;
            $nearestWipe['connect_text'] = 'connect ' . $serverForButtons->ip . ':' . $serverForButtons->port;
        }

        return $this->render('index', [
            'monthsData'   => $monthsData,
            'currentYear'  => $year,
            'currentMonth' => $month,
            'shownMonths'  => $months,
            'nearestWipe'  => $nearestWipe,
            'recentWipe'   => $recentWipe,
        ]);
    }

    /**
     * Как mergeDayEvents в WipeCalendarClient.tsx: в одном времени «Глобальный вайп» + «Вайп карты» → «Вайп»;
     * дубликаты бейджей с одинаковым непустым link отбрасываются. Имена через Yii::t (в Next в фильтре захардкожен русский).
     *
     * @param array<int, array<string, mixed>> $dayEvents
     * @return array<int, array<string, mixed>>
     */
    private function mergeDayEventsForDisplay(array $dayEvents): array
    {
        if ($dayEvents === []) {
            return $dayEvents;
        }

        $nameGlobal = Yii::t('common', 'Глобальный вайп');
        $nameMap    = Yii::t('common', 'Вайп карты');
        $nameWipe   = Yii::t('common', 'Вайп');

        $byTime = [];
        foreach ($dayEvents as $e) {
            $t = (string)($e['time'] ?? '');
            $byTime[$t][] = $e;
        }
        $sortedTimes = array_keys($byTime);
        sort($sortedTimes, SORT_STRING);

        $result = [];
        foreach ($sortedTimes as $time) {
            $list = $byTime[$time];
            $wipeEvents = [];
            $rest = [];
            foreach ($list as $e) {
                $n = (string)($e['name'] ?? '');
                if ($n === $nameGlobal || $n === $nameMap) {
                    $wipeEvents[] = $e;
                } else {
                    $rest[] = $e;
                }
            }
            if ($wipeEvents !== []) {
                $allBadges = [];
                foreach ($wipeEvents as $e) {
                    if (!empty($e['badges']) && is_array($e['badges'])) {
                        foreach ($e['badges'] as $b) {
                            $allBadges[] = $b;
                        }
                    }
                }
                $seenLinks = [];
                $badges = [];
                foreach ($allBadges as $badge) {
                    $link = isset($badge['link']) ? trim((string)$badge['link']) : '';
                    if ($link !== '') {
                        if (isset($seenLinks[$link])) {
                            continue;
                        }
                        $seenLinks[$link] = true;
                    }
                    $badges[] = $badge;
                }
                $result[] = [
                    'name'        => $nameWipe,
                    'link'        => null,
                    'time'        => $time,
                    'is_official' => false,
                    'is_global'   => false,
                    'badges'      => $badges,
                    'desc'        => null,
                ];
            }
            foreach ($rest as $e) {
                $result[] = $e;
            }
        }

        return $result;
    }

    /**
     * Недельные вайпы (wipe_type=7 и tag=monday): по полосам дней месяца 1–7, 8–14, 15–21, 22–28, 29–31…
     * Нечётная «неделя месяца» → глобальный слот; чётная → вайп карты. В новом месяце счёт с начала.
     */
    private function weeklySevenDaySlotIsGlobal(DateTimeImmutable $dt): bool
    {
        $dayOfMonth = (int)$dt->format('j');
        $weekOfMonth = (int)floor(($dayOfMonth - 1) / 7) + 1;

        return ($weekOfMonth % 2 === 1);
    }

    /**
     * Каждый понедельник в $mapTime для серверов с tag = monday — то же чередование глобал / вайп карты, что у 7-дневных.
     * ISO-недели из $blockedIsoWeeks пропускаются (совместимость с глобальной блокировкой; сейчас передаётся []).
     */
    private function addMondaysWeekly(
        array &$byDateTime,
        array $monthStarts,
        DateTimeImmutable $afterLastMonth,
        $mapTime,
        Servers $s,
        DateTimeZone $tz,
        array $blockedIsoWeeks = []
    ) {
        $emptyBucket = [
            'official' => false, 'global' => false, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
            'names7' => [], 'names14' => [], 'title' => null, 'link' => null, 'names' => [],
        ];
        foreach ($monthStarts as $mStart) {
            $firstMon = $this->firstWeekdayOfMonth($mStart, 1);
            $dt = new DateTimeImmutable($firstMon->format('Y-m-d') . ' ' . $mapTime, $tz);
            while ($dt < $afterLastMonth) {
                $iso = $dt->format('o-\WW');
                if (!isset($blockedIsoWeeks[$iso])) {
                    $key = $dt->format('Y-m-d H:i:s');
                    if (isset($byDateTime[$key]) && !empty($byDateTime[$key]['official'])) {
                        $dt = $dt->modify('+7 days');
                        continue;
                    }
                    if ($this->weeklySevenDaySlotIsGlobal($dt)) {
                        if (!isset($byDateTime[$key])) {
                            $byDateTime[$key] = [
                                'official' => false, 'global' => true, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
                                'names7' => [], 'names14' => [], 'title' => Yii::t('common', 'Глобальный вайп'), 'link' => '/servers', 'names' => [],
                            ];
                        }
                        $byDateTime[$key]['names7'][] = $s->monitoring_name ?: $s->name;
                    } else {
                        if (!isset($byDateTime[$key])) {
                            $byDateTime[$key] = $emptyBucket;
                        }
                        $byDateTime[$key]['servers'][$s->id] = [
                            'id' => (int)$s->id,
                            'name' => $s->name,
                            'monitoring_name' => $s->monitoring_name,
                            'link' => $s->getLink('stats'),
                            'text_ip' => $s->text_ip,
                            'wt' => 71,
                        ];
                        $byDateTime[$key]['weekly7_count']++;
                        $byDateTime[$key]['names7'][] = $s->monitoring_name ?: $s->name;
                    }
                }
                $dt = $dt->modify('+7 days');
            }
        }
    }

    private function addWeeklyMapWipes(array &$byDateTime, array $monthStarts, DateTimeImmutable $afterLastMonth, $mapTime, Servers $s, DateTimeZone $tz)
    {
        $weekday = (int)($s->wipe_weekday ?? 5);
        if ($weekday < 1 || $weekday > 7) {
            $weekday = 5;
        }
        $emptyBucket = [
            'official' => false, 'global' => false, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
            'names7' => [], 'names14' => [], 'title' => null, 'link' => null, 'names' => [],
        ];
        foreach ($monthStarts as $mStart) {
            $dt = $this->firstWeekdayOfMonth($mStart, $weekday);
            $dt = new DateTimeImmutable($dt->format('Y-m-d') . ' ' . $mapTime, $tz);
            while ($dt < $afterLastMonth) {
                $key = $dt->format('Y-m-d H:i:s');
                $isGlobalSlot = $this->weeklySevenDaySlotIsGlobal($dt);
                if ($isGlobalSlot) {
                    if (!isset($byDateTime[$key])) {
                        $byDateTime[$key] = [
                            'official' => false, 'global' => true, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
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

    private function addBiweeklyMapWipes(array &$byDateTime, array $monthStarts, DateTimeImmutable $afterLastMonth, array $globalDates, $globalTime, $mapTime, Servers $s, DateTimeZone $tz)
    {
        $weekday = (int)($s->wipe_weekday ?? 5);
        if ($weekday < 1 || $weekday > 7) {
            $weekday = 5;
        }
        $emptyBucket = [
            'official' => false, 'global' => false, 'servers' => [], 'weekly7_count' => 0, 'biweekly14_count' => 0,
            'names7' => [], 'names14' => [], 'title' => null, 'link' => null, 'names' => [],
        ];
        foreach ($monthStarts as $mStart) {
            $firstDay = $this->firstWeekdayOfMonth($mStart, $weekday);
            $dtFirst = new DateTimeImmutable($firstDay->format('Y-m-d') . ' ' . $mapTime, $tz);
            if ($dtFirst < $afterLastMonth) {
                $key = $dtFirst->format('Y-m-d H:i:s');
                if (!isset($byDateTime[$key])) {
                    $byDateTime[$key] = array_merge([], $emptyBucket);
                }
                $byDateTime[$key]['global'] = true;
                $byDateTime[$key]['biweekly14_count'] = ($byDateTime[$key]['biweekly14_count'] ?? 0) + 1;
                $byDateTime[$key]['names14'][] = $s->monitoring_name ?: $s->name;
                if (!isset($byDateTime[$key]['title']) || $byDateTime[$key]['title'] === null) {
                    $byDateTime[$key]['title'] = Yii::t('common', 'Глобальный вайп');
                    $byDateTime[$key]['link'] = '/servers';
                }
            }
            $dt = $dtFirst->modify('+14 days');
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

    private function firstWeekdayOfMonth(DateTimeImmutable $monthStart, $weekday)
    {
        $firstN = (int)$monthStart->format('N');
        $delta  = (int)$weekday - $firstN;
        if ($delta < 0) {
            $delta += 7;
        }
        return $monthStart->modify('+' . $delta . ' day');
    }

    private function buildMonthGrid(DateTimeImmutable $monthStart, array $eventsByDay, DateTimeZone $tz)
    {
        $year   = (int)$monthStart->format('Y');
        $month  = (int)$monthStart->format('n');
        $daysIn = (int)$monthStart->format('t');
        $firstN = (int)$monthStart->format('N');
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

        return [
            'title'   => $title,
            'year'    => $year,
            'month'   => $month,
            'weeks'   => $weeks,
            'prevUrl' => ['/wipe-calendar/index', 'year' => (int)$prev->format('Y'), 'month' => (int)$prev->format('n')],
            'nextUrl' => ['/wipe-calendar/index', 'year' => (int)$next->format('Y'), 'month' => (int)$next->format('n')],
        ];
    }
}
