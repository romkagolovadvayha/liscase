<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use DateTimeImmutable;
use DateTimeZone;
use common\models\servers\Servers;

class WipeCalendarController extends Controller
{
    public function actionIndex($year = null, $month = null, $months = 1)
    {
        $this->view->params['page'] = 'wipe-calendar';
        $tz  = new DateTimeZone(Yii::$app->timeZone ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);

        $year   = $year   ? (int)$year  : (int)$now->format('Y');
        $month  = $month  ? (int)$month : (int)$now->format('n');
        $months = (int)max(1, (int)$months);

        // === SEO ===
        $title = Yii::t('common', 'Календарь вайпов Rust — официальный и серверные вайпы');
        $desc  = Yii::t('common', 'Календарь вайпов Rust: глобальный вайп в первый четверг месяца и регулярные вайпы карт (7-дневные и 14-дневные). Навигация по месяцам без перезагрузки, точные даты и время.');
        $keys  = Yii::t('common', 'календарь вайпов rust, rust вайп, глобальный вайп, вайп карты, 7-дневные, 14-дневные, расписание вайпов rust');

        $this->view->title = $title;
        $this->view->registerMetaTag(['name' => 'description', 'content' => $desc]);
        $this->view->registerMetaTag(['name' => 'keywords', 'content' => $keys]);
        $this->view->registerMetaTag(['property' => 'og:title', 'content' => $title]);
        $this->view->registerMetaTag(['property' => 'og:description', 'content' => $desc]);
        $this->view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary']);

        // Время событий
        $globalTime = '21:00:00'; // глобал (четверг)
        $mapTime    = '16:00:00'; // вайп карты (пятница)

        // Диапазон месяцев
        $firstMonthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $monthStarts = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStarts[] = $firstMonthStart->modify('+' . $i . ' month');
        }
        $afterLastMonth = $firstMonthStart->modify('+' . $months . ' month');

        // Активные сервера
        /** @var Servers[] $activeServers */
        $activeServers = Servers::find()
                                ->select(['id', 'name', 'monitoring_name', 'tag', 'wipe_type', 'status', 'sort'])
                                ->andWhere(['status' => Servers::STATUS_ACTIVE])
                                ->orderBy(['sort' => SORT_ASC])
                                ->all();

        // активные, которые участвуют в пятничных вайпах (исключаем тип 30)
        $non30Active = array_values(array_filter($activeServers, function ($s) {
            return (int)$s->wipe_type !== 30;
        }));
        $countNon30 = count($non30Active);

        // === 1) Глобальные (первый четверг каждого месяца) + блок-недели
        $globalByDateTime = []; // 'Y-m-d H:i:s' => true
        $blockedIsoWeeks  = []; // 'YYYY-WW' => true
        foreach ($monthStarts as $mStart) {
            $firstThu = $this->firstWeekdayOfMonth($mStart, 4); // 1=Пн..4=Чт..7=Вс
            $gDT = new DateTimeImmutable($firstThu->format('Y-m-d') . ' ' . $globalTime, $tz);
            $globalByDateTime[$gDT->format('Y-m-d H:i:s')] = true;
            $blockedIsoWeeks[$gDT->format('o-\WW')] = true;
        }

        // === 2) Слоты по точному времени
        // структура слота:
        // 'global' => bool,
        // 'servers' => [id => ['id','name','monitoring_name','link','wt']],
        // 'weekly7_count' => int,
        // 'biweekly14_count' => int,
        // 'names7' => string[], 'names14' => string[],
        // 'title' => string|null, 'link' => string|null, 'names' => string[]
        $byDateTime = [];

        // Глобалы сразу «все сервера»
        foreach ($globalByDateTime as $dtStr => $_) {
            $byDateTime[$dtStr] = [
                'global'           => true,
                'servers'          => [],
                'weekly7_count'    => 0,
                'biweekly14_count' => 0,
                'names7'           => [],
                'names14'          => [],
                'title'            => Yii::t('common', 'Глобальный вайп на всех серверах'),
                'link'             => '/servers',
                'names'            => [Yii::t('common', 'все сервера')],
            ];
        }

        // === 3) Пятничные вайпы карт по типу (учитывая блок-недели)
        foreach ($non30Active as $s) {
            $wt = (int)$s->wipe_type;
            if ($wt === 7) {
                $this->addFridaysWeekly($byDateTime, $monthStarts, $afterLastMonth, $blockedIsoWeeks, $mapTime, $s, $tz);
            } elseif ($wt === 14) {
                $this->addFridaysBiWeekly($byDateTime, $monthStarts, $afterLastMonth, $blockedIsoWeeks, $mapTime, $s, $tz);
            }
        }

        // === 4) Схлопывание/агрегация
        foreach ($byDateTime as $dtStr => &$bucket) {
            if (!empty($bucket['global'])) {
                continue;
            }
            $hasServers = !empty($bucket['servers']);
            $count7  = isset($bucket['weekly7_count']) ? (int)$bucket['weekly7_count'] : 0;
            $count14 = isset($bucket['biweekly14_count']) ? (int)$bucket['biweekly14_count'] : 0;

            // (A) Если в слоте есть и 7-дневные, и 14-дневные,
            // и их суммарно столько же, сколько всех non-30 активных → «Вайп на всех серверах»
            if ($hasServers && $countNon30 > 0 && ($count7 + $count14) === $countNon30 && $count7 > 0 && $count14 > 0) {
                $bucket['title'] = Yii::t('common', 'Вайп на всех серверах');
                $bucket['link']  = '/servers';
                // подготовим списки имён для бейджей
                $names7  = array_values(array_unique($bucket['names7']));
                $names14 = array_values(array_unique($bucket['names14']));
                $bucket['names']  = []; // не используем
                $bucket['names7'] = $names7;
                $bucket['names14'] = $names14;
                $bucket['servers'] = []; // одна запись
                continue;
            }

            // (B) Только 7-дневные (их >= 2) → «Вайп карты на недельных серверах»
            if ($hasServers && $count7 >= 2 && $count14 === 0) {
                $bucket['title'] = Yii::t('common', 'Вайп карты на недельных серверах');
                $bucket['link']  = '/servers';
                $bucket['names'] = array_values(array_unique($bucket['names7']));
                $bucket['servers'] = [];
                continue;
            }

            // (остальные случаи — как прежде: покасерверно или «на всех серверах» если совпало)
            if ($hasServers && ($count7 + $count14) === $countNon30 && $countNon30 > 0) {
                $bucket['title'] = Yii::t('common', 'Вайп на всех серверах');
                $bucket['link']  = '/servers';
                $bucket['names'] = [Yii::t('common', 'все сервера')];
                $bucket['servers'] = [];
            }
        }
        unset($bucket);

        // === 5) Готовим events для вью
        // event:
        // - name, link, time
        // - is_official, is_global
        // - badges: массив бейджей {class, text}
        $events = [];
        foreach ($byDateTime as $dtStr => $bucket) {
            $dt      = new DateTimeImmutable($dtStr, $tz);
            $dayKey  = $dt->format('Y-m-d');
            $timeTxt = $dt->format('H:i');

            $badges = [];

            if (!empty($bucket['global'])) {
                $badges[] = ['class' => 'badge-global', 'text' => Yii::t('common', 'все сервера')];

                $events[$dayKey][] = [
                    'name'        => $bucket['title'],
                    'link'        => $bucket['link'],
                    'time'        => $timeTxt,
                    'is_official' => true,
                    'is_global'   => true,
                    'badges'      => $badges,
                    'desc'        => null,
                ];
                continue;
            }

            // Схлопнутые
            if (isset($bucket['title']) && empty($bucket['servers'])) {
                // кейс (A): на всех серверах, с разными типами
                if (!empty($bucket['names7']) || !empty($bucket['names14'])) {
                    if (!empty($bucket['names7'])) {
                        $badges[] = ['class' => 'badge-weekly7',   'text' => Yii::t('common', '{list}', ['list' => implode(', ', $bucket['names7'])])];
                    }
                    if (!empty($bucket['names14'])) {
                        $badges[] = ['class' => 'badge-biweekly14','text' => Yii::t('common', '{list}', ['list' => implode(', ', $bucket['names14'])])];
                    }
                } elseif (!empty($bucket['names'])) {
                    // кейс (B) и «все сервера»
                    $badges[] = ['class' => ($bucket['names'][0] === Yii::t('common', 'все сервера') ? 'badge-status' : 'badge-weekly7'),
                                 'text'  => $bucket['names'][0] === Yii::t('common', 'все сервера')
                                     ? Yii::t('common', 'все сервера')
                                     : implode(', ', $bucket['names'])];
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
                        ['class' => ($srv['wt'] === 7 ? 'badge-weekly7' : 'badge-biweekly14'),
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

        // === 6) Сетки месяцев
        $monthsData = [];
        foreach ($monthStarts as $mStart) {
            $monthsData[] = $this->buildMonthGrid($mStart, $events, $tz);
        }

        return $this->render('index', [
            'monthsData'   => $monthsData,
            'currentYear'  => $year,
            'currentMonth' => $month,
            'shownMonths'  => $months,
        ]);
    }

    /** Еженедельные (тип 7) */
    private function addFridaysWeekly(array &$byDateTime, array $monthStarts, DateTimeImmutable $afterLastMonth, array $blockedIsoWeeks, $mapTime, Servers $s, DateTimeZone $tz)
    {
        foreach ($monthStarts as $mStart) {
            $firstFri = $this->firstWeekdayOfMonth($mStart, 5);
            $dt = new DateTimeImmutable($firstFri->format('Y-m-d') . ' ' . $mapTime, $tz);

            while ($dt < $afterLastMonth) {
                $iso = $dt->format('o-\WW');
                if (!isset($blockedIsoWeeks[$iso])) {
                    $key = $dt->format('Y-m-d H:i:s');
                    if (!isset($byDateTime[$key])) {
                        $byDateTime[$key] = [
                            'global'           => false,
                            'servers'          => [],
                            'weekly7_count'    => 0,
                            'biweekly14_count' => 0,
                            'names7'           => [],
                            'names14'          => [],
                            'title'            => null,
                            'link'             => null,
                            'names'            => [],
                        ];
                    }
                    $byDateTime[$key]['servers'][$s->id] = [
                        'id'              => (int)$s->id,
                        'name'            => $s->name,
                        'monitoring_name' => $s->monitoring_name,
                        'link'            => $s->getLink('maps'),
                        'wt'              => 7,
                    ];
                    $byDateTime[$key]['weekly7_count']++;
                    $byDateTime[$key]['names7'][] = $s->monitoring_name ?: $s->name;
                }
                $dt = $dt->modify('+7 days');
            }
        }
    }

    /** Раз в 2 недели (тип 14) */
    private function addFridaysBiWeekly(array &$byDateTime, array $monthStarts, DateTimeImmutable $afterLastMonth, array $blockedIsoWeeks, $mapTime, Servers $s, DateTimeZone $tz)
    {
        foreach ($monthStarts as $mStart) {
            $firstFri = $this->firstWeekdayOfMonth($mStart, 5);
            $startDT  = new DateTimeImmutable($firstFri->format('Y-m-d') . ' ' . $mapTime, $tz);
            if (isset($blockedIsoWeeks[$startDT->format('o-\WW')])) {
                $startDT = $startDT->modify('+7 days');
            }
            $dt = $startDT;
            while ($dt < $afterLastMonth) {
                $iso = $dt->format('o-\WW');
                if (!isset($blockedIsoWeeks[$iso])) {
                    $key = $dt->format('Y-m-d H:i:s');
                    if (!isset($byDateTime[$key])) {
                        $byDateTime[$key] = [
                            'global'           => false,
                            'servers'          => [],
                            'weekly7_count'    => 0,
                            'biweekly14_count' => 0,
                            'names7'           => [],
                            'names14'          => [],
                            'title'            => null,
                            'link'             => null,
                            'names'            => [],
                        ];
                    }
                    $byDateTime[$key]['servers'][$s->id] = [
                        'id'              => (int)$s->id,
                        'name'            => $s->name,
                        'monitoring_name' => $s->monitoring_name,
                        'link'            => $s->getLink('maps'),
                        'wt'              => 14,
                    ];
                    $byDateTime[$key]['biweekly14_count']++;
                    $byDateTime[$key]['names14'][] = $s->monitoring_name ?: $s->name;
                }
                $dt = $dt->modify('+14 days');
            }
        }
    }

    /** Первый N-й день недели месяца: $weekday 1=Пн .. 7=Вс */
    private function firstWeekdayOfMonth(DateTimeImmutable $monthStart, $weekday)
    {
        $firstN = (int)$monthStart->format('N');
        $delta  = (int)$weekday - $firstN;
        if ($delta < 0) $delta += 7;
        return $monthStart->modify('+' . $delta . ' day');
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
