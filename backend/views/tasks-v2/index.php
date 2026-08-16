<?php

use common\models\tasks_v2\TaskV2;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \common\models\battle_pass\BattlePassSeason[] $seasons */
/** @var int $seasonId */

$this->title = Yii::t('common', 'Задания v2');
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = $this->title;

$type = Yii::$app->request->get('type');
$isActive = Yii::$app->request->get('is_active');
$search = Yii::$app->request->get('search');
?>
<div class="tasks-v2-index-page">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="tasks-v2-index-section">
        <div class="tasks-v2-index-filters">
            <?= Html::beginForm(['tasks-v2/index'], 'get', ['class' => 'tasks-v2-index-form']) ?>
            <div class="tasks-v2-index-filters__row">
                <div class="tasks-v2-index-filters__field">
                    <label class="tasks-v2-index-label" for="type"><?= Yii::t('common', 'Тип') ?></label>
                    <?= Html::dropDownList('type', $type, ['' => Yii::t('common', 'Все')] + TaskV2::getTypeList(), ['class' => 'ds-select', 'id' => 'type']) ?>
                </div>
                <div class="tasks-v2-index-filters__field">
                    <label class="tasks-v2-index-label" for="season_id"><?= Yii::t('common', 'Сезон Battle Pass') ?></label>
                    <?= Html::dropDownList('season_id', $seasonId, ['' => Yii::t('common', 'Все сезоны')] + ArrayHelper::map($seasons, 'id', 'name'), ['class' => 'ds-select', 'id' => 'season_id']) ?>
                </div>
                <div class="tasks-v2-index-filters__field">
                    <label class="tasks-v2-index-label" for="is_active"><?= Yii::t('common', 'Статус') ?></label>
                    <?= Html::dropDownList('is_active', $isActive, ['' => Yii::t('common', 'Все'), '1' => Yii::t('common', 'Активные'), '0' => Yii::t('common', 'Неактивные')], ['class' => 'ds-select', 'id' => 'is_active']) ?>
                </div>
                <div class="tasks-v2-index-filters__field">
                    <label class="tasks-v2-index-label" for="search"><?= Yii::t('common', 'Поиск') ?></label>
                    <?= Html::textInput('search', $search, ['class' => 'ds-input', 'id' => 'search', 'placeholder' => Yii::t('common', 'Название...')]) ?>
                </div>
                <div class="tasks-v2-index-filters__actions">
                    <?= Html::submitButton('<i class="fas fa-filter"></i> ' . Yii::t('common', 'Применить'), ['class' => 'ds-btn ds-btn--primary']) ?>
                    <?= Html::a('<i class="fas fa-redo"></i> ' . Yii::t('common', 'Сбросить'), ['tasks-v2/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
                </div>
            </div>
            <?= Html::endForm() ?>
        </div>

        <div class="tasks-v2-index-cards-wrap">
            <?php if ($seasonId > 0): ?>
                <p class="tasks-v2-sort-hint"><i class="fas fa-grip-lines"></i> Перетаскивайте карточки, чтобы изменить номера. Бесплатные задания должны оставаться перед VIP.</p>
            <?php endif; ?>
            <?= ListView::widget([
                'dataProvider' => $dataProvider,
                'itemView' => '_card',
                'layout' => "{items}\n<div class=\"tasks-v2-index-pager\">{pager}</div>",
                'itemOptions' => ['class' => 'tasks-v2-index-card-wrap', 'tag' => 'div'],
                'options' => ['class' => 'tasks-v2-index-cards', 'tag' => 'div'],
                'emptyText' => '<div class="tasks-v2-index-empty">' . Yii::t('common', 'Заданий не найдено') . '</div>',
                'emptyTextOptions' => ['class' => 'tasks-v2-index-empty-wrap'],
            ]) ?>
        </div>
    </div>
</div>

<?php if ($seasonId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.tasks-v2-index-cards');
    if (!container) return;
    let dragged = null;

    container.addEventListener('dragstart', function (event) {
        const card = event.target.closest('.tasks-v2-index-card[draggable="true"]');
        if (!card) return;
        dragged = card.closest('.tasks-v2-index-card-wrap');
        dragged.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
    });
    container.addEventListener('dragover', function (event) {
        if (!dragged) return;
        event.preventDefault();
        const target = event.target.closest('.tasks-v2-index-card-wrap');
        if (!target || target === dragged) return;
        const rect = target.getBoundingClientRect();
        const after = event.clientY > rect.top + rect.height / 2 || event.clientX > rect.left + rect.width / 2;
        container.insertBefore(dragged, after ? target.nextSibling : target);
    });
    container.addEventListener('dragend', async function () {
        if (!dragged) return;
        dragged.classList.remove('is-dragging');
        dragged = null;
        const params = new URLSearchParams();
        params.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');
        params.append('season_id', '<?= (int)$seasonId ?>');
        container.querySelectorAll('.tasks-v2-index-card[data-task-id]').forEach(function (card) {
            params.append('order[]', card.dataset.taskId);
        });
        const response = await fetch('<?= Url::to(['sort-battle-pass']) ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: params.toString(),
        });
        const result = await response.json();
        if (!result.success) {
            alert(result.message || 'Не удалось сохранить порядок.');
            window.location.reload();
            return;
        }
        window.location.reload();
    });
});
</script>
<?php endif; ?>

<style>
.tasks-v2-index-page {
    padding: 16px 24px;
    background: hsl(0 0% 10% / 1);
    min-height: 100%;
}
.tasks-v2-index-section {
    background: hsl(0 0% 11.8% / 1);
    border: 1px solid hsl(0 0% 15.3% / 1);
    border-radius: 10px;
    overflow: hidden;
}
.tasks-v2-index-filters {
    padding: 16px 20px;
    border-bottom: 1px solid hsl(0 0% 15.3% / 1);
}
.tasks-v2-index-filters__row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 20px;
}
.tasks-v2-index-filters__field {
    display: flex;
    flex-direction: column;
    min-width: 140px;
}
.tasks-v2-index-label {
    font-size: 12px;
    color: hsl(0 0% 55%);
    margin-bottom: 4px;
}
.tasks-v2-index-filters__field .ds-input,
.tasks-v2-index-filters__field .ds-select {
    min-width: 160px;
}
.tasks-v2-index-filters__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.tasks-v2-index-cards-wrap {
    padding: 16px 20px;
}
.tasks-v2-sort-hint { margin: 0 0 12px; color: hsl(0 0% 62%); font-size: 12px; }
.tasks-v2-index-card[draggable="true"] { cursor: grab; }
.tasks-v2-index-card-wrap.is-dragging { opacity: .45; }
.tasks-v2-index-cards {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.tasks-v2-index-card-wrap { margin: 0; }
.tasks-v2-index-card {
    display: flex;
    flex-direction: column;
    background: hsl(0 0% 15% / 1);
    border: 1px solid hsl(0 0% 20% / 1);
    border-radius: 10px;
    overflow: hidden;
    min-height: 0;
}
.tasks-v2-index-card__preview {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    background: hsl(0 0% 18% / 1);
    overflow: hidden;
}
.tasks-v2-index-card__preview-link {
    position: absolute;
    inset: 0;
    display: block;
}
.tasks-v2-index-card__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.tasks-v2-index-card__no-photo {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: hsl(0 0% 50%);
    font-size: 14px;
}
.tasks-v2-index-card__id {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 4px;
}
.tasks-v2-index-card__body {
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.tasks-v2-index-card__title {
    font-weight: 600;
    color: #fff;
    font-size: 14px;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
}
.tasks-v2-index-card__meta {
    font-size: 12px;
    color: hsl(0 0% 65%);
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
}
.tasks-v2-index-card__reward {
    font-size: 12px;
    color: hsl(0 0% 75%);
}
.tasks-v2-index-card__reward img { vertical-align: middle; margin-right: 4px; }
.tasks-v2-index-card__stats {
    font-size: 11px;
    color: hsl(0 0% 55%);
    display: flex;
    gap: 12px;
}
.tasks-v2-index-card__badges { display: flex; flex-wrap: wrap; gap: 6px; }
.tasks-v2-index-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
}
.tasks-v2-index-card__actions .ds-btn { flex-shrink: 0; }
.tasks-v2-index-empty-wrap {
    padding: 24px;
    text-align: center;
    color: hsl(0 0% 55%);
}
.tasks-v2-index-pager {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid hsl(0 0% 15.3% / 1);
    display: flex;
    justify-content: center;
}
.tasks-v2-index-pager .pagination {
    margin: 0;
    background: transparent;
}
.tasks-v2-index-pager .page-link {
    background: hsl(0 0% 20% / 1) !important;
    color: #fff !important;
    border-color: hsl(0 0% 15.3% / 1) !important;
}
.tasks-v2-index-pager .page-item.active .page-link {
    background: hsl(200 70% 50% / 1) !important;
    border-color: hsl(200 70% 50% / 1) !important;
}
</style>
