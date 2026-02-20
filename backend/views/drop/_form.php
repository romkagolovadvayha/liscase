<?php

use common\models\box\Drop;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\widgets\Pjax;

/** @var Drop $model */
$this->registerJs(<<<JS
    $(document).on('change', '#dropform-drop_type',  function() {
        $('#box-form').submit();
    });
    $(document).on('click', '#drop-form-submit',  function() {
        $('#drop-form-is-submit-button').val(1);
    });
    
    $(document).on('pjax:success', '#drop-drop-form-pjax', function(event, data, status, xhr) {
        try {
            var response = typeof data === 'string' ? JSON.parse(data) : data;
            if (response && response.success && response.dropId) {
                $('#modal-dialog').modal('hide');
                $('.modal-backdrop').remove();
                $.get('/drop/items-list?id=' + response.dropId, function(html) {
                    $('#drop-items-list').replaceWith(html);
                });
            }
        } catch(e) {}
    });
    
    $(document).on('click', '.delete-drop-item', function(e) {
        e.preventDefault();
        var link = $(this);
        if (!confirm('Вы уверены, что хотите удалить этот предмет?')) return false;
        $.ajax({
            url: link.attr('href'),
            type: 'POST',
            data: { _csrf: yii.getCsrfToken() },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $.get('/drop/items-list?id=' + link.attr('href').match(/dropId=(\d+)/)[1], function(html) {
                        $('#drop-items-list').replaceWith(html);
                    });
                } else { alert(response.message || 'Ошибка при удалении'); }
            },
            error: function() { alert('Ошибка при удалении'); }
        });
        return false;
    });
JS
);
?>

<?php $form = ActiveForm::begin([
    'enableClientValidation' => false,
    'enableAjaxValidation'   => false,
    'id' => 'box-form',
    'options' => ['data-pjax' => true, 'enctype' => 'multipart/form-data', 'class' => 'drop-form drop-form--compact flex flex-col lg:flex-row min-h-0 flex-1'],
]); ?>
<?php Pjax::begin(['id' => 'box-form-pjax', 'enablePushState' => false, 'options' => ['class' => 'drop-form-layout']]); ?>

<!-- Основная колонка: компактное расположение -->
<div class="flex-1 min-w-0 p-4 lg:p-6 drop-form-content">
<?= $form->field($model, 'name', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control']) ?>

<?= $form->field($model, 'description', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 2, 'class' => 'ds-textarea form-control']) ?>

<!-- Превью + загрузка изображения в одной строке -->
<div class="drop-form-preview-row mb-2">
    <?php if (!$model->isNewRecord && $model->image()): ?>
    <div class="drop-form-preview-img">
        <img src="<?= Html::encode($model->image()) ?>" alt="Превью" />
    </div>
    <?php endif; ?>
    <div class="drop-form-preview-upload">
        <?= $form->field($model, 'preview_file', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->fileInput(['class' => 'ds-input form-control']) ?>
    </div>
</div>

<div class="drop-form-row-three flex flex-nowrap gap-3 mb-2">
    <div class="flex-1 min-w-0"><?= $form->field($model, 'discount', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['value' => $model->isNewRecord ? 0 : $model->discount, 'class' => 'ds-input form-control']) ?></div>
    <div class="flex-1 min-w-0"><?= $form->field($model, 'count', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['value' => $model->isNewRecord ? 1 : $model->count, 'class' => 'ds-input form-control']) ?></div>
    <div class="flex-1 min-w-0"><?= $form->field($model, 'floating_price_percent', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['value' => $model->isNewRecord ? 0 : $model->floating_price_percent, 'class' => 'ds-input form-control']) ?></div>
</div>

<?php if (in_array($model->drop_type, [Drop::TYPE_COMMAND, Drop::TYPE_VIP])): ?>
<?= $form->field($model, 'command', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{hint}{error}'])->textarea(['class' => 'ds-textarea form-control', 'rows' => 2])
    ->hint($model->drop_type == Drop::TYPE_VIP ? 'Команда будет выполнена на сервере при выдаче VIP. Используйте %STEAMID% для подстановки Steam ID пользователя.' : '') ?>
<?php endif; ?>

<?php if (in_array($model->drop_type, [Drop::TYPE_SET])): ?>
<div class="row mb-2">
    <div class="col-md-4">
        <div class="ds-select-wrapper">
            <?= $form->field($model, 'full_only', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList([1 => 'Да', 0 => 'Нет'], ['class' => 'ds-select form-control']) ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>
    </div>
</div>
<div class="form-group mb-2">
    <?= $form->field($model, 'show_main_block', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->checkbox(['label' => 'Выводить целиком']) ?>
</div>
<?php endif; ?>

<?php if (in_array($model->drop_type, [Drop::TYPE_SET, Drop::TYPE_SELECT])): ?>
<div class="form-group mb-2">
    <?php if ($model->isNewRecord): ?>
        <span class="ds-btn ds-btn--primary ds-btn--sm ds-btn--disabled" title="Сначала сохраните предмет" style="opacity:0.6; cursor:not-allowed; pointer-events:none;"><i class="fas fa-plus"></i> Добавить предмет</span>
    <?php else: ?>
        <a href="/drop-drop/create?dropId=<?= (int)$model->id ?>" class="ds-btn ds-btn--primary ds-btn--sm show-modal-link" data-toggl="modal" data-target="modal-dialog" data-title="Добавить предмет" data-pjax="0"><i class="fas fa-plus"></i> Добавить предмет</a>
    <?php endif; ?>
</div>
<?php if (!$model->isNewRecord): ?>
<div id="drop-items-container">
    <?= $this->render('_items_list', ['model' => $model]) ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?= $this->render('list-drop-stat', ['dropId' => $model->id]) ?>

<div class="mt-3 flex flex-wrap gap-2 items-center">
    <?= Html::activeHiddenInput($model, 'isSubmit', ['id' => 'drop-form-is-submit-button', 'value' => 0]) ?>
    <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary', 'id' => 'drop-form-submit']) ?>
</div>

</div><!-- /.flex-1 основная колонка -->

<!-- Правая колонка: идентификатор, статус в магазине, выводить целиком, цена -->
<aside class="drop-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
    <div class="p-4 flex-1 flex flex-col">
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Параметры</h3>
            <div class="space-y-3">
            <div>
                <label class="text-xs text-gray-400 mb-1 block">Идентификатор</label>
                <?= $form->field($model, 'rust_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">Short key</label>
                <?= $form->field($model, 'eng_name', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">Категория</label>
                <div class="ds-select-wrapper">
                    <?= $form->field($model, 'category_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(\common\models\box\Category::getCategoryList(), ['class' => 'ds-select']) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">Тип предмета</label>
                <div class="ds-select-wrapper">
                    <?= $form->field($model, 'drop_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(Drop::getDropTypesList(), ['class' => 'ds-select']) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
            <div>
                <?= $form->field($model, 'market_status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->checkbox(['label' => 'В магазине', 'value' => 1, 'uncheck' => 0]) ?>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">Цена</label>
                <?= $form->field($model, 'price', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">Вайп блок</label>
                <div class="ds-select-wrapper">
                    <?= $form->field($model, 'blocked_hour', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList([
                        '' => 'Нет блока',
                        2 => '2 ч',
                        4 => '4 ч',
                        6 => '6 ч',
                        12 => '12 ч',
                        24 => '24 ч',
                    ], ['class' => 'ds-select']) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
            <div>
                <?= $form->field($model, 'is_blocked_building', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->checkbox(['label' => 'Запретить выводить в зоне чужого шкафа']) ?>
            </div>
        </div>
        </div>
    </div>
</aside>

<?php Pjax::end(); ?>
<?php ActiveForm::end(); ?>
