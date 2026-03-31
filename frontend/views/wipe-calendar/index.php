<?php
/**
 * @var array      $monthsData
 * @var int        $currentYear
 * @var int        $currentMonth
 * @var int        $shownMonths
 * @var array|null $nearestWipe  ['date', 'time', 'name', 'link', 'ip', 'port', 'connect_href', 'connect_text' для кнопок]
 * @var array|null $recentWipe   ['date', 'time', 'name', 'link']
 */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

$this->params['breadcrumbs'][] = Yii::t('common', 'Календарь вайпов Rust');

/** Классы бейджей как на prostoj.store /wipe-calendar (additional.scss). */
$wipeCalBadgeClass = static function (?string $apiClass): string {
    $c = (string)$apiClass;
    if ($c === 'badge-global') {
        return 'wipe-calendar-badge wipe-calendar-badge--badge-global';
    }
    if ($c === 'badge-map-wipe') {
        return 'wipe-calendar-badge wipe-calendar-badge--badge-map-wipe';
    }
    return 'wipe-calendar-badge';
};
?>

<div class="page-wipe-calendar">

    <header class="page-header">
        <h1 class="page-title">
            <?= Yii::t('common', 'Календарь вайпов Rust') ?>
        </h1>
        <p class="page-subtitle">
            <?= Yii::t('common', 'Здесь собраны предстоящие и прошедшие события: обновление игры — первый четверг месяца в 21:00; глобальные и вайпы карт — по расписанию 7- и 14-дневных серверов (обычно 16:00, день недели задаётся у сервера).') ?>
        </p>
    </header>

    <?php Pjax::begin([
                          'id'              => 'wipe-calendar-pjax',
                          'timeout'         => 8000,
                          'enablePushState' => true,
                      ]); ?>

    <?php if (!empty($nearestWipe) || !empty($recentWipe)): ?>
        <section class="nearest-wipe">
            <?php if (!empty($nearestWipe)): ?>
                <h2 class="nearest-wipe__title"><?= Yii::t('common', 'Ближайший вайп') ?></h2>
                <p class="nearest-wipe__info">
                    <?php if (!empty($nearestWipe['is_today'])): ?>
                        <span class="nearest-wipe__date"><?= Yii::t('common', 'Сегодня в {time} МСК', ['time' => $nearestWipe['time']]) ?></span>
                    <?php else: ?>
                        <span class="nearest-wipe__date"><?= htmlspecialchars($nearestWipe['date'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="nearest-wipe__time"><?= htmlspecialchars($nearestWipe['time'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($nearestWipe['link'])): ?>
                        <?php
                        $nwLink = $nearestWipe['link'];
                        $nwResolved = strpos((string)$nwLink, '/') === 0 ? Url::to($nwLink) : (string)$nwLink;
                        $nwExternal = strpos((string)$nwLink, 'http') === 0;
                        ?>
                        <a href="<?= Html::encode($nwResolved) ?>" class="nearest-wipe__link"<?= $nwExternal ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($nearestWipe['name'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <span class="nearest-wipe__name"><?= htmlspecialchars($nearestWipe['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($nearestWipe['connect_href']) && !empty($nearestWipe['connect_text'])): ?>
                    <div class="nearest-wipe__actions">
                        <a class="button button-primary button-size__s h-36" href="<?= htmlspecialchars($nearestWipe['connect_href'], ENT_QUOTES, 'UTF-8') ?>"><span class="button__text"><?= Yii::t('common', 'Подключиться к серверу') ?></span></a>
                        <button type="button"
                                class="button button-secondary button-size__s h-36 btn-clipboard nearest-wipe__copy"
                                data-clipboard-text="<?= htmlspecialchars($nearestWipe['connect_text'], ENT_QUOTES, 'UTF-8') ?>"
                                data-message="<?= htmlspecialchars(Yii::t('common', 'IP адрес скопирован в буфер обмена!'), ENT_QUOTES, 'UTF-8') ?>"
                                title="<?= htmlspecialchars(Yii::t('common', 'Скопировать IP'), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-copy nearest-wipe__copy-icon" aria-hidden="true"></i>
                            <span class="button__text"><?= Yii::t('common', 'Скопировать IP') ?></span>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($recentWipe)): ?>
                <h3 class="nearest-wipe__subtitle"><?= Yii::t('common', 'Недавний вайп') ?></h3>
                <p class="nearest-wipe__info">
                    <?php if (!empty($recentWipe['is_today'])): ?>
                        <span class="nearest-wipe__date"><?= Yii::t('common', 'Сегодня в {time} МСК', ['time' => $recentWipe['time']]) ?></span>
                    <?php else: ?>
                        <span class="nearest-wipe__date"><?= htmlspecialchars($recentWipe['date'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="nearest-wipe__time"><?= htmlspecialchars($recentWipe['time'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($recentWipe['link'])): ?>
                        <?php
                        $rwLink = $recentWipe['link'];
                        $rwResolved = strpos((string)$rwLink, '/') === 0 ? Url::to($rwLink) : (string)$rwLink;
                        $rwExternal = strpos((string)$rwLink, 'http') === 0;
                        ?>
                        <a href="<?= Html::encode($rwResolved) ?>" class="nearest-wipe__link"<?= $rwExternal ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($recentWipe['name'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <span class="nearest-wipe__name"><?= htmlspecialchars($recentWipe['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="calendar-wrap">
        <?php foreach ($monthsData as $m): ?>
            <section class="calendar">

                <div class="calendar-header">
                    <a class="button button-secondary button-size__s h-36"
                       data-pjax="1"
                       href="<?= Url::to($m['prevUrl']) ?>">
                        <span class="button__text">← <?= Yii::t('common', 'Предыдущий') ?></span>
                    </a>

                    <h2 class="calendar-title">
                        <?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <a class="button button-secondary button-size__s h-36"
                       data-pjax="1"
                       href="<?= Url::to($m['nextUrl']) ?>">
                        <span class="button__text"><?= Yii::t('common', 'Следующий') ?> →</span>
                    </a>
                </div>

                <div class="calendar-grid">
                    <?php
                    $weekdays = [
                        Yii::t('common', 'Пн'),
                        Yii::t('common', 'Вт'),
                        Yii::t('common', 'Ср'),
                        Yii::t('common', 'Чт'),
                        Yii::t('common', 'Пт'),
                        Yii::t('common', 'Сб'),
                        Yii::t('common', 'Вс'),
                    ];
                    foreach ($weekdays as $w) {
                        echo '<div class="calendar-weekday">'.$w.'</div>';
                    }
                    ?>

                    <?php foreach ($m['weeks'] as $week): ?>
                        <?php foreach ($week as $cell): ?>
                            <?php
                            $classes = ['calendar-cell'];
                            if (!empty($cell['isToday']))      $classes[] = 'today';
                            if (!empty($cell['isOtherMonth'])) $classes[] = 'other-month';
                            ?>
                            <?php
                            $cellHasOfficial = false;
                            if (!empty($cell['events'])) {
                                foreach ($cell['events'] as $ev0) {
                                    if (!empty($ev0['is_official'])) {
                                        $cellHasOfficial = true;
                                        break;
                                    }
                                }
                            }
                            if ($cellHasOfficial) {
                                $classes[] = 'has-official-update';
                            }
                            ?>
                            <div class="<?= implode(' ', $classes) ?>">
                                <div class="calendar-daynum"><?= (int)$cell['day'] ?></div>

                                <?php if (!empty($cell['events'])): ?>
                                    <?php foreach ($cell['events'] as $ev): ?>
                                        <article class="event<?= !empty($ev['is_official']) ? ' event--official-update' : '' ?>">
                                            <div class="event-head">
                                                <?php if (!empty($ev['time']) && $ev['time'] !== '00:00'): ?>
                                                    <span class="time"><?= htmlspecialchars($ev['time'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>

                                                <?php
                                                $evLink = $ev['link'] ?? null;
                                                if ($evLink === null || $evLink === '') {
                                                    $evResolved = '';
                                                } elseif (strpos((string)$evLink, '/') === 0) {
                                                    $evResolved = Url::to($evLink);
                                                } else {
                                                    $evResolved = (string)$evLink;
                                                }
                                                ?>
                                                <?php if ($evResolved !== ''): ?>
                                                    <a href="<?= Html::encode($evResolved) ?>"<?= strpos((string)$evLink, 'http') === 0 ? ' target="_blank" rel="noopener"' : '' ?>>
                                                        <?= htmlspecialchars($ev['name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($ev['name'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </div>

                                            <div class="event-meta">
                                                <?php if (!empty($ev['is_official'])): ?>
                                                    <span class="wipe-calendar-badge wipe-calendar-badge--badge-patch"><span class="wipe-calendar-badge__inner"><?= Yii::t('common', 'патч / обновление') ?></span></span>
                                                <?php endif; ?>

                                                <?php if (!empty($ev['badges'])): ?>
                                                    <?php foreach ($ev['badges'] as $b): ?>
                                                        <?php
                                                        $bLink = $b['link'] ?? null;
                                                        if ($bLink === null || $bLink === '') {
                                                            $bResolved = '';
                                                        } elseif (strpos((string)$bLink, '/') === 0) {
                                                            $bResolved = Url::to($bLink);
                                                        } else {
                                                            $bResolved = (string)$bLink;
                                                        }
                                                        $bUiClass = $wipeCalBadgeClass($b['class'] ?? '');
                                                        $bText = htmlspecialchars($b['text'], ENT_QUOTES, 'UTF-8');
                                                        ?>
                                                        <?php if ($bResolved !== ''): ?>
                                                            <a class="<?= Html::encode($bUiClass) ?> wipe-calendar-badge--link" href="<?= Html::encode($bResolved) ?>"<?= strpos((string)$bLink, 'http') === 0 ? ' target="_blank" rel="noopener"' : '' ?>><span class="wipe-calendar-badge__inner"><?= $bText ?></span></a>
                                                        <?php else: ?>
                                                            <span class="<?= Html::encode($bUiClass) ?>"><span class="wipe-calendar-badge__inner"><?= $bText ?></span></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php elseif (!empty($ev['status'])): ?>
                                                    <span class="wipe-calendar-badge wipe-calendar-badge--badge-neutral"><span class="wipe-calendar-badge__inner"><?= htmlspecialchars($ev['status'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($ev['desc'])): ?>
                                                <p class="event-desc"><?= nl2br(htmlspecialchars($ev['desc'], ENT_QUOTES, 'UTF-8')) ?></p>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

            </section>
        <?php endforeach; ?>
    </div>

    <?php Pjax::end(); ?>

    <section class="wipe-calendar-legend-yii" aria-label="<?= Html::encode(Yii::t('common', 'Цвета бейджей календаря')) ?>">
        <h2 class="wipe-calendar-legend-yii__title"><?= Yii::t('common', 'Цвета и обозначения') ?></h2>
        <div class="wipe-calendar-legend-colors">
            <div class="wipe-calendar-legend-color-item">
                <span class="wipe-calendar-badge wipe-calendar-badge--badge-global"><span class="wipe-calendar-badge__inner"><?= Yii::t('common', 'Глобальный вайп') ?></span></span>
                <span class="wipe-calendar-legend-color-item__text"><?= Yii::t('common', 'Серверы в глобальном слоте (7/14-дневные циклы) — фиолетовый бейдж.') ?></span>
            </div>
            <div class="wipe-calendar-legend-color-item">
                <span class="wipe-calendar-badge wipe-calendar-badge--badge-map-wipe"><span class="wipe-calendar-badge__inner"><?= Yii::t('common', 'Вайп карты') ?></span></span>
                <span class="wipe-calendar-legend-color-item__text"><?= Yii::t('common', 'Вайп только карты в свой день недели — зелёный бейдж; по клику — страница сервера.') ?></span>
            </div>
        </div>
    </section>

    <section class="seo-block">
        <h2><?= Yii::t('common', 'Как читать календарь вайпов') ?></h2>
        <ul class="mt-12">
            <li><?= Yii::t('common', 'Событие «Обновление игры» (четверг 21:00) — выход патча Facepunch, это не вайп карты на наших серверах.') ?></li>
            <li class="mt-6"><?= Yii::t('common', '30-дневные (месячные) серверы в календаре не показываются — так же, как в API /v1/wipe-calendar и на prostoj.store.') ?></li>
            <li class="mt-6"><?= Yii::t('common', '«Глобальный вайп» — для 7-дневных серверов: по полосам дней месяца (1–7, 8–14, 15–21, 22–28…) нечётная «неделя» — глобальный слот, чётная — вайп карты; в следующем месяце счёт с начала. Для 14-дневных — первый день вайпа в месяце и далее через 14 дней.') ?></li>
            <li class="mt-6"><?= Yii::t('common', '«Вайп карты» — вайп только карты в 16:00 в свой день недели; по клику на бейдж сервера можно перейти на его страницу.') ?></li>
            <li class="mt-6"><?= Yii::t('common', 'Как на prostoj.store: события «Глобальный вайп» и «Вайп карты» в одно и то же время показываются одной карточкой «Вайп» с общим списком бейджей (дубликаты ссылок убираются).') ?></li>
        </ul>

        <h3 class="mt-40"><?= Yii::t('common', 'Зачем это нужно') ?></h3>
        <p class="mt-12"><?= Yii::t('common', 'Планируйте старт, собирайте команду и заходите сразу после вайпа — так вы займёте лучшие места на карте и быстрее разовьётесь.') ?></p>
    </section>

</div>

<style>
    /* ====== БАЗОВАЯ ВЁРСТКА С УДОБНЫМИ ОТСТУПАМИ ====== */

    .page-wipe-calendar {
        color: var(--text-main);
        padding: 16px;
        display: grid;
        gap: 24px;
    }

    .page-header {
        display: grid;
        gap: 8px;
    }

    .page-title {
        margin: 0;
        font-size: 28px;
        line-height: 1.2;
    }

    .page-subtitle {
        margin: 0;
        opacity: .85;
    }

    /* ====== КАЛЕНДАРЬ ====== */

    .calendar-wrap {
        display: grid;
        gap: 24px;
    }

    .calendar {
        border-radius: var(--block-radius);
        background-color: var(--background-secondary);
        border: 1px solid var(--background-teritiary);
        overflow: hidden;
        display: grid;
        gap: 0;
    }

    .calendar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid var(--background-teritiary);
    }

    .calendar-title {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }

    .calendar-weekday {
        background: var(--background-teritiary);
        padding: 10px 8px;
        text-align: center;
        font-size: 12px;
        font-weight: 600;
        border-bottom: 1px solid var(--background-teritiary);
    }

    .calendar-cell {
        min-height: 130px;
        border-right: 1px solid var(--background-teritiary);
        border-bottom: 1px solid var(--background-teritiary);
        padding: 10px;
        position: relative;
        background: transparent;
        display: grid;
        gap: 10px;
    }

    .calendar-cell.empty {
        background: transparent;
        opacity: .5;
    }

    .calendar-cell.today {
        background-color: var(--background-teritiary);
        box-shadow: inset 0 0 0 2px var(--accent, rgba(59, 130, 246, 0.6));
    }

    .calendar-daynum {
        position: absolute;
        top: 6px;
        right: 8px;
        font-size: 12px;
        opacity: .7;
    }

    /* ====== СОБЫТИЯ ====== */

    .event {
        display: grid;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 10px;
        background-color: var(--background-teritiary);
        border: 1px solid var(--background-teritiary);
    }

    .calendar-cell .event + .event {
        margin-top: 6px;
    }

    .event-head {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 10px;
        align-items: baseline;
    }

    .event .time {
        font-weight: 700;
    }

    .event-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px;
    }

    .event-desc {
        margin: 0;
        opacity: .95;
        line-height: 1.35;
    }

    /* ====== БЕЙДЖИ (как prostoj-frontend additional.scss → .wipe-calendar-badge) ====== */

    .wipe-calendar-badge {
        display: inline-table;
        font-size: 9px;
        padding: 2px 5px;
        background: var(--background-teritiary, #e5e7eb);
        color: var(--text-secondary, #6b7280);
        border-radius: 4px;
        font-weight: 500;
        border: 1px solid transparent;
        white-space: nowrap;
        vertical-align: middle;
    }

    .wipe-calendar-badge__inner {
        display: table-cell;
        vertical-align: middle;
        line-height: 1;
    }

    .wipe-calendar-badge--badge-global {
        background: rgba(99, 102, 241, 0.15);
        color: #6366f1;
        border-color: rgba(99, 102, 241, 0.3);
    }

    .wipe-calendar-badge--badge-map-wipe {
        background: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }

    .wipe-calendar-badge--badge-patch {
        background: rgba(22, 163, 74, 0.12);
        color: #15803d;
        border-color: rgba(34, 197, 94, 0.28);
    }

    .wipe-calendar-badge--badge-neutral {
        background: rgba(229, 231, 235, 0.6);
        color: var(--text-secondary, #6b7280);
        border-color: rgba(156, 163, 175, 0.35);
    }

    .wipe-calendar-badge--link {
        text-decoration: none;
        cursor: pointer;
        transition: opacity 0.15s;
    }

    .wipe-calendar-badge--link:hover {
        opacity: 0.85;
        text-decoration: none;
    }

    /* Легенда под календарём */
    .wipe-calendar-legend-yii {
        padding: 14px 16px;
        border-radius: var(--block-radius);
        background-color: var(--background-secondary);
        border: 1px solid var(--background-teritiary);
    }

    .wipe-calendar-legend-yii__title {
        margin: 0 0 10px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .wipe-calendar-legend-colors {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .wipe-calendar-legend-color-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        line-height: 1.45;
        color: var(--text-main);
        opacity: 0.92;
    }

    .wipe-calendar-legend-color-item__text {
        flex: 1;
        min-width: 0;
    }

    .calendar-cell.has-official-update {
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.35);
    }
    .event--official-update {
        border-color: rgba(34, 197, 94, 0.35);
    }
    /* ====== АДАПТИВ ====== */

    @media (max-width: 900px) {
        .calendar-grid { font-size: 12px; }
        .calendar-cell { min-height: 110px; }
    }

    /* соседние дни (пред/след месяц) */
    .calendar-cell.other-month {
        opacity: .6;                /* серим всё содержимое */
        background-color: var(--background-secondary-dark);
    }

    /* оставляем подсветку «сегодня» даже если это соседний месяц */
    .calendar-cell.today {
        background-color: var(--background-teritiary);
        box-shadow: inset 0 0 0 2px var(--accent, rgba(59, 130, 246, 0.6));
    }

    /* ====== БЛИЖАЙШИЙ ВАЙП ====== */
    .nearest-wipe {
        padding: 14px 18px;
        margin-bottom: 24px;
        border-radius: var(--block-radius);
        background-color: var(--background-secondary);
        border: 1px solid var(--background-teritiary);
    }
    .nearest-wipe__title {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: 600;
        opacity: .9;
    }
    .nearest-wipe__subtitle {
        margin: 16px 0 8px 0;
        font-size: 13px;
        font-weight: 600;
        opacity: .85;
    }
    .nearest-wipe__info {
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px 14px;
    }
    .nearest-wipe__actions {
        margin-top: 14px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .nearest-wipe__copy {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .nearest-wipe__copy-icon {
        font-size: 12px;
        opacity: .9;
    }
    .nearest-wipe__date { font-weight: 600; }
    .nearest-wipe__time {
        font-weight: 700;
        color: var(--accent, #3b82f6);
    }
    .nearest-wipe__link {
        color: var(--link-color, #3b82f6);
        text-decoration: none;
    }
    .nearest-wipe__link:hover { text-decoration: underline; }
    .nearest-wipe__name { font-weight: 500; }
</style>

<?= $this->render('@frontend/views/layouts/_wipe-calendar-promo-script') ?>
