<?php
/**
 * @var array      $monthsData
 * @var int        $currentYear
 * @var int        $currentMonth
 * @var int        $shownMonths
 * @var array|null $nearestWipe  ['date', 'time', 'name', 'link', 'ip', 'port', 'connect_href', 'connect_text' для кнопок]
 * @var array|null $recentWipe   ['date', 'time', 'name', 'link']
 */

use yii\helpers\Url;
use yii\widgets\Pjax;

$this->params['breadcrumbs'][] = Yii::t('common', 'Календарь вайпов Rust');
?>

<div class="page-wipe-calendar">

    <header class="page-header">
        <h1 class="page-title">
            <?= Yii::t('common', 'Календарь вайпов Rust') ?>
        </h1>
        <p class="page-subtitle">
            <?= Yii::t('common', 'Здесь собраны предстоящие и прошедшие вайпы по всем серверам проекта. Официальный вайп — в первый четверг каждого месяца.') ?>
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
                        <a href="<?= htmlspecialchars($nearestWipe['link'], ENT_QUOTES, 'UTF-8') ?>" class="nearest-wipe__link" target="_blank" rel="noopener"><?= htmlspecialchars($nearestWipe['name'], ENT_QUOTES, 'UTF-8') ?></a>
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
                        <a href="<?= htmlspecialchars($recentWipe['link'], ENT_QUOTES, 'UTF-8') ?>" class="nearest-wipe__link" target="_blank" rel="noopener"><?= htmlspecialchars($recentWipe['name'], ENT_QUOTES, 'UTF-8') ?></a>
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
                            <div class="<?= implode(' ', $classes) ?>">
                                <div class="calendar-daynum"><?= (int)$cell['day'] ?></div>

                                <?php if (!empty($cell['events'])): ?>
                                    <?php foreach ($cell['events'] as $ev): ?>
                                        <article class="event">
                                            <div class="event-head">
                                                <?php if (!empty($ev['time']) && $ev['time'] !== '00:00'): ?>
                                                    <span class="time"><?= htmlspecialchars($ev['time'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($ev['link'])): ?>
                                                    <a href="<?= htmlspecialchars($ev['link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                        <?= htmlspecialchars($ev['name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($ev['name'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </div>

                                            <div class="event-meta">
                                                <?php if (!empty($ev['is_official'])): ?>
                                                    <span class="badge badge-official"><?= Yii::t('common', 'официальный') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($ev['is_global'])): ?>
                                                    <span class="badge badge-global"><?= Yii::t('common', 'глобальный') ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($ev['badges'])): ?>
                                                    <?php foreach ($ev['badges'] as $b): ?>
                                                        <span class="badge <?= htmlspecialchars($b['class'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($b['text'], ENT_QUOTES, 'UTF-8') ?>
            </span>
                                                    <?php endforeach; ?>
                                                <?php elseif (!empty($ev['status'])): ?>
                                                    <span class="badge badge-status"><?= htmlspecialchars($ev['status'], ENT_QUOTES, 'UTF-8') ?></span>
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

    <section class="seo-block">
        <h2><?= Yii::t('common', 'Как читать календарь вайпов') ?></h2>
        <ul class="mt-12">
            <li><?= Yii::t('common', 'Бейдж «официальный» отмечает, что выходит официальный глобальный патч (обычно первый четверг месяца).') ?></li>
            <li class="mt-6"><?= Yii::t('common', 'Бейдж «глобальный» означает вайп на всех серверах проекта. В таком случае дополнительно показывается бейдж «все сервера».') ?></li>
            <li class="mt-6"><?= Yii::t('common', 'Если одновременно вайп у 7-дневных и 14-дневных серверов, событие объединяется как «Вайп на всех серверах», а списки серверов выводятся отдельными бейджами.') ?></li>
            <li class="mt-6"><?= Yii::t('common', 'Когда вайп только на 7-дневных серверах, событие показывается как «Вайп карты на недельных серверах» с бейджем-списком этих серверов.') ?></li>
            <li class="mt-6"><?= Yii::t('common', 'Серверы с вайпом по понедельникам отображаются отдельно с бейджем по понедельникам.') ?></li>
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
        align-items: flex-start;
        gap: 6px;
    }

    .event-desc {
        margin: 0;
        opacity: .95;
        line-height: 1.35;
    }

    /* ====== БЕЙДЖИ ====== */

    .badge {
        display: inline-block;
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 999px;
    }

    .badge-official { background: rgba(138, 253, 158, 0.43); }
    .badge-global   { background: rgba(219, 234, 254, 0.43); }
    .badge-status   { background: rgba(229, 231, 235, 0.43); }

    /* новые для групп */
    .badge-weekly7       { background: rgba(255, 233, 160, 0.45); }   /* тёплый для 7-дневных (пятница) */
    .badge-biweekly14    { background: rgba(180, 220, 255, 0.45); }   /* холодный для 14-дневных */
    .badge-weekly-monday { background: rgba(216, 180, 254, 0.45); } /* фиолетовый для недельных по понедельникам */
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
