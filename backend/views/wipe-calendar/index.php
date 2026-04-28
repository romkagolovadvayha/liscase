<?php

/** @var yii\web\View $this */

use backend\assets\WipeCalendarAsset;
use common\models\wipe_calendar\WipeCalendarEvent;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

WipeCalendarAsset::register($this);

$this->params['breadcrumbs'][] = $this->title;

$typeList = WipeCalendarEvent::typeList();
$pageCfg = [
    'eventsUrl' => Url::to(['/wipe-calendar/events']),
    'serversUrl' => Url::to(['/wipe-calendar/servers']),
    'highlightsUrl' => Url::to(['/wipe-calendar/highlights']),
    'saveUrl' => Url::to(['/wipe-calendar/save-event']),
    'deleteUrl' => Url::to(['/wipe-calendar/delete-event']),
    'csrf' => Yii::$app->request->csrfToken,
    'csrfParam' => Yii::$app->request->csrfParam,
];
$this->registerJs('window.WipeCalendarPage = ' . Json::encode($pageCfg) . ';', View::POS_HEAD);
?>

<div class="wipe-cal-page w-full max-w-[1600px] mx-auto px-4 pb-8">
    <div class="wipe-cal-toolbar-extra">
        <button type="button" class="ds-btn ds-btn--primary ds-btn--sm" id="wipe-cal-btn-add">
            <i class="fas fa-plus"></i> Добавить событие
        </button>
        <span class="text-xs text-gray-500">
            <span class="inline-block w-3 h-3 rounded-sm align-middle mr-1 bg-[hsl(340_35%_30%_/_1)]"></span> праздник РФ
            ·
            <span class="inline-block w-3 h-3 rounded-sm align-middle mr-1 bg-[hsl(0_0%_18%_/_1)] border border-gray-600"></span> выходной
        </span>
    </div>

    <div class="wipe-cal-layout">
        <div class="wipe-cal-servers">
            <div class="wipe-cal-servers-title">Серверы</div>
            <div id="wipe-cal-server-list"></div>
            <p class="wipe-cal-hint">Перетащите сервер на дату — по умолчанию «Вайп карты»; в модалке можно выбрать «Глобальный вайп» (сервер обязателен для обоих типов). Статусы: включён / выключен / скоро / закрыт.</p>
        </div>
        <div class="wipe-cal-calendar-wrap">
            <div id="wipe-cal-fc"></div>
        </div>
    </div>
</div>

<div id="wipe-cal-modal-backdrop" class="wipe-cal-modal-backdrop hidden" aria-hidden="true"></div>
<div id="wipe-cal-modal" class="wipe-cal-modal hidden" role="dialog" aria-modal="true" aria-labelledby="wipe-cal-modal-title">
    <h3 id="wipe-cal-modal-title">Событие</h3>
    <div id="wipe-cal-form-error" class="wipe-cal-error" style="display: none;"></div>

    <input type="hidden" id="wipe-cal-field-id" value="">

    <div class="wipe-cal-field">
        <label for="wipe-cal-field-date">Дата</label>
        <input type="date" id="wipe-cal-field-date" required>
    </div>

    <div class="wipe-cal-field">
        <label for="wipe-cal-field-time">Время</label>
        <input type="time" id="wipe-cal-field-time" value="16:00" required>
        <div class="wipe-cal-time-presets">
            <button type="button" class="wipe-cal-time-preset" data-time="16:00">16:00</button>
            <button type="button" class="wipe-cal-time-preset" data-time="19:00">19:00</button>
            <button type="button" class="wipe-cal-time-preset" data-time="21:00">21:00</button>
        </div>
    </div>

    <div class="wipe-cal-field">
        <label for="wipe-cal-field-type">Тип события</label>
        <select id="wipe-cal-field-type" required>
            <?php foreach ($typeList as $val => $label): ?>
                <option value="<?= Html::encode($val) ?>"><?= Html::encode($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="wipe-cal-field" id="wipe-cal-field-server-wrap">
        <label for="wipe-cal-field-server">Сервер</label>
        <select id="wipe-cal-field-server">
            <option value="">—</option>
        </select>
    </div>

    <div class="wipe-cal-field" id="wipe-cal-field-title-wrap">
        <label for="wipe-cal-field-title">Название (обязательно для «Другое событие»)</label>
        <input type="text" id="wipe-cal-field-title" maxlength="255" placeholder="Например, стрим / турнир">
    </div>

    <div class="wipe-cal-modal-actions">
        <button type="button" class="ds-btn ds-btn--danger ds-btn--sm hidden" id="wipe-cal-btn-delete">Удалить</button>
        <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" id="wipe-cal-btn-close">Отмена</button>
        <button type="button" class="ds-btn ds-btn--primary ds-btn--sm" id="wipe-cal-btn-save">Сохранить</button>
    </div>
</div>
