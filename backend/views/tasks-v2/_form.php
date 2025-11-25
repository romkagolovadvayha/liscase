<?php

use common\models\box\Drop;
use common\models\tasks_v2\TaskV2;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;

/** @var yii\web\View $this */
/** @var TaskV2 $model */
/** @var yii\widgets\ActiveForm $form */

$this->title = $model->isNewRecord 
    ? Yii::t('common', 'Создать задание')
    : Yii::t('common', 'Редактировать задание');

$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Задания v2'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$dropFormat = new JsExpression("
    function(item) {
        try {
            var model = JSON.parse(item.text);
            return '<div class=\"drop-select-item\"><img class=\"kv-icon-image\" src=\"' + (model.image || '') + '\"/><span>' + model.name + '</span></div>';
        } catch {
            return item.text;
        }
    }
");

?>
<div class="tasks-v2-form">
    <?php $form = ActiveForm::begin([
        'id' => 'tasks-v2-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

    <div class="card">
        <div class="card-header">
            <h4><?= Html::encode($this->title) ?></h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>
                    
                    <?= $form->field($model, 'short_description')->textarea(['rows' => 2]) ?>
                    
                    <?= $form->field($model, 'full_description')->textarea(['rows' => 6]) ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'type')->dropDownList(TaskV2::getTypeList(), [
                                'id' => 'task-type',
                                'prompt' => Yii::t('common', 'Выберите тип...')
                            ]) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'check_type')->dropDownList(TaskV2::getCheckTypeList(), [
                                'id' => 'task-check-type',
                                'prompt' => Yii::t('common', 'Выберите тип проверки...')
                            ]) ?>
                        </div>
                    </div>
                    
                    <div id="check-params-container" style="display: none;">
                        <h5><?= Yii::t('common', 'Параметры проверки') ?></h5>
                        <div id="check-params-content"></div>
                    </div>
                    
                    <h5 class="mt-4"><?= Yii::t('common', 'Награда') ?></h5>
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'reward_type')->dropDownList(TaskV2::getRewardTypeList(), [
                                'id' => 'task-reward-type',
                                'prompt' => Yii::t('common', 'Выберите тип награды...')
                            ]) ?>
                        </div>
                        <div class="col-md-6" id="reward-item-container" style="display: none;">
                            <?= $form->field($model, 'reward_item_id')->widget(Select2::class, [
                                'data' => Drop::getList(),
                                'options' => [
                                    'placeholder' => Yii::t('common', 'Выберите товар...'),
                                ],
                                'pluginOptions' => [
                                    'templateResult' => $dropFormat,
                                    'templateSelection' => $dropFormat,
                                    'escapeMarkup' => new JsExpression('function(m){return m}'),
                                    'allowClear' => true,
                                ],
                            ]) ?>
                        </div>
                        <div class="col-md-6" id="reward-currency-container" style="display: none;">
                            <?= $form->field($model, 'reward_currency')->dropDownList([
                                'personal' => Yii::t('common', 'Лицевой счет'),
                                'skins' => Yii::t('common', 'Скины'),
                            ], ['prompt' => Yii::t('common', 'Выберите тип баланса...')]) ?>
                        </div>
                        <div class="col-md-6" id="reward-amount-container" style="display: none;">
                            <?= $form->field($model, 'reward_amount')->textInput(['type' => 'number', 'step' => '0.01']) ?>
                        </div>
                    </div>
                    
                    <h5 class="mt-4"><?= Yii::t('common', 'Лимиты') ?></h5>
                    <div class="row">
                        <div class="col-md-6" id="per-user-limit-container" style="display: none;">
                            <?= $form->field($model, 'per_user_limit')->textInput(['type' => 'number'])
                                ->hint(Yii::t('common', 'Оставьте пустым для неограниченного количества выполнений на пользователя')) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'global_limit')->textInput(['type' => 'number'])
                                ->hint(Yii::t('common', 'Оставьте пустым для неограниченного общего количества выполнений')) ?>
                        </div>
                        <div class="col-md-6" id="max-progress-container" style="display: none;">
                            <?= $form->field($model, 'max_progress')->textInput(['type' => 'number', 'min' => 1])
                                ->hint(Yii::t('common', 'Максимальный прогресс для отображения (для заданий с прогрессом). Оставьте пустым, если прогресс вычисляется автоматически.')) ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <h5><?= Yii::t('common', 'Изображение') ?></h5>
                    <?php if ($model->image_path): ?>
                        <div class="mb-3">
                            <img src="/<?= Html::encode(ltrim($model->image_path, '/')) ?>" 
                                 alt="<?= Html::encode($model->title) ?>"
                                 style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 8px;">
                            <br>
                            <?= Html::a(
                                '<i class="fas fa-trash"></i> ' . Yii::t('common', 'Удалить изображение'),
                                ['delete-image', 'id' => $model->id],
                                [
                                    'class' => 'btn btn-sm btn-danger',
                                    'data-confirm' => Yii::t('common', 'Вы уверены, что хотите удалить изображение?'),
                                    'data-method' => 'post',
                                    'style' => 'display: none;', // Пока не реализовано
                                ]
                            ) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?= $form->field($model, 'imageFile')->fileInput(['accept' => 'image/*'])
                        ->hint(Yii::t('common', 'Рекомендуемый размер: 400x300px или 16:9')) ?>
                    
                    <h5 class="mt-4"><?= Yii::t('common', 'UX-настройки') ?></h5>
                    <?= $form->field($model, 'button_text')->textInput(['maxlength' => true])
                        ->hint(Yii::t('common', 'По умолчанию: "Проверить"')) ?>
                    
                    <h6 class="mt-3"><?= Yii::t('common', 'Дополнительные кнопки-ссылки') ?></h6>
                    <div id="extra-buttons-container">
                        <?php
                        $extraButtons = $model->extra_buttons ?? [];
                        if (empty($extraButtons)) {
                            $extraButtons = [['label' => '', 'url' => '']];
                        }
                        foreach ($extraButtons as $index => $button): ?>
                            <div class="extra-button-row mb-2" data-index="<?= $index ?>">
                                <div class="input-group">
                                    <?= Html::textInput("extra_buttons[{$index}][label]", $button['label'] ?? '', [
                                        'class' => 'form-control',
                                        'placeholder' => Yii::t('common', 'Название кнопки')
                                    ]) ?>
                                    <?= Html::textInput("extra_buttons[{$index}][url]", $button['url'] ?? '', [
                                        'class' => 'form-control',
                                        'placeholder' => Yii::t('common', 'URL')
                                    ]) ?>
                                    <?php if (count($extraButtons) > 1): ?>
                                    <button type="button" class="btn btn-outline-danger remove-extra-button">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-danger remove-extra-button" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-sm btn-secondary" id="add-extra-button">
                            <i class="fas fa-plus"></i> <?= Yii::t('common', 'Добавить кнопку') ?>
                        </button>
                    </div>
                    
                    <h5 class="mt-4"><?= Yii::t('common', 'Видимость') ?></h5>
                    <?= $form->field($model, 'is_visible_for_guests')->checkbox()
                        ->hint(Yii::t('common', 'Отображать задание для неавторизованных пользователей (не смогут выполнить)')) ?>
                    
                    <?= $form->field($model, 'is_active')->checkbox() ?>
                    
                    <?= $form->field($model, 'sort')->textInput(['type' => 'number'])
                        ->hint(Yii::t('common', 'Меньше значение = выше в списке')) ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <?= Html::submitButton(
                Yii::t('common', 'Сохранить'),
                ['class' => 'btn btn-success']
            ) ?>
            <?= Html::a(
                Yii::t('common', 'Отмена'),
                ['index'],
                ['class' => 'btn btn-secondary']
            ) ?>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const taskType = document.getElementById('task-type');
    const checkType = document.getElementById('task-check-type');
    const rewardType = document.getElementById('task-reward-type');
    const checkParamsContainer = document.getElementById('check-params-container');
    const checkParamsContent = document.getElementById('check-params-content');
    const rewardItemContainer = document.getElementById('reward-item-container');
    const rewardCurrencyContainer = document.getElementById('reward-currency-container');
    const rewardAmountContainer = document.getElementById('reward-amount-container');
    const perUserLimitContainer = document.getElementById('per-user-limit-container');
    const maxProgressContainer = document.getElementById('max-progress-container');
    const extraButtonsContainer = document.getElementById('extra-buttons-container');
    
    // Показываем/скрываем лимит на пользователя для многоразовых заданий
    function updatePerUserLimit() {
        if (taskType.value === '<?= TaskV2::TYPE_REPEATABLE ?>') {
            perUserLimitContainer.style.display = 'block';
        } else {
            perUserLimitContainer.style.display = 'none';
        }
    }
    
    // Показываем/скрываем поле max_progress для заданий с прогрессом
    function updateMaxProgress() {
        const isRepeatable = taskType.value === '<?= TaskV2::TYPE_REPEATABLE ?>';
        const isOneTimeWithStats = taskType.value === '<?= TaskV2::TYPE_ONE_TIME ?>' && 
                                   checkType.value === '<?= TaskV2::CHECK_TYPE_STATISTICS_PARAM ?>';
        
        if (isRepeatable || isOneTimeWithStats) {
            maxProgressContainer.style.display = 'block';
        } else {
            maxProgressContainer.style.display = 'none';
        }
    }
    
    // Обновляем поля награды в зависимости от типа
    function updateRewardFields() {
        if (rewardType.value === '<?= TaskV2::REWARD_TYPE_ITEM ?>') {
            rewardItemContainer.style.display = 'block';
            rewardCurrencyContainer.style.display = 'none';
            rewardAmountContainer.style.display = 'block';
        } else if (rewardType.value === '<?= TaskV2::REWARD_TYPE_CURRENCY ?>') {
            rewardItemContainer.style.display = 'none';
            rewardCurrencyContainer.style.display = 'block';
            rewardAmountContainer.style.display = 'block';
        } else {
            rewardItemContainer.style.display = 'none';
            rewardCurrencyContainer.style.display = 'none';
            rewardAmountContainer.style.display = 'none';
        }
    }
    
    // Обновляем параметры проверки в зависимости от типа проверки
    function updateCheckParams() {
        const checkTypeValue = checkType.value;
        checkParamsContent.innerHTML = '';
        
        if (!checkTypeValue) {
            checkParamsContainer.style.display = 'none';
            return;
        }
        
        checkParamsContainer.style.display = 'block';
        
        // Параметры для разных типов проверок
        const checkParamsData = {
            '<?= TaskV2::CHECK_TYPE_VK_SUBSCRIBE_GROUP ?>': [
                {name: 'group_id', label: '<?= Html::encode(Yii::t('common', 'ID группы VK')) ?>', type: 'number'}
            ],
            '<?= TaskV2::CHECK_TYPE_KILL_BOTS_COUNT ?>': [
                {name: 'count', label: '<?= Html::encode(Yii::t('common', 'Требуемое количество')) ?>', type: 'number'}
            ],
            '<?= TaskV2::CHECK_TYPE_INVITE_FRIEND ?>': [
                {name: 'count', label: '<?= Html::encode(Yii::t('common', 'Требуемое количество')) ?>', type: 'number'}
            ],
            '<?= TaskV2::CHECK_TYPE_STATISTICS_PARAM ?>': [
                {name: 'stat_key', label: '<?= Html::encode(Yii::t('common', 'Ключ параметра статистики')) ?>', type: 'text'},
                {name: 'required_value', label: '<?= Html::encode(Yii::t('common', 'Требуемое значение')) ?>', type: 'number'},
                {name: 'server_id', label: '<?= Html::encode(Yii::t('common', 'ID сервера (0 = текущий сервер)')) ?>', type: 'number'},
                {name: 'sum_all_servers', label: '<?= Html::encode(Yii::t('common', 'Суммировать по всем серверам')) ?>', type: 'checkbox'}
            ],
        };
        
        if (checkParamsData[checkTypeValue]) {
            const currentParams = <?= Json::encode($model->check_params ?? []) ?>;
            checkParamsData[checkTypeValue].forEach(function(param) {
                const field = document.createElement('div');
                field.className = 'mb-3';
                
                if (param.type === 'checkbox') {
                    const checked = currentParams[param.name] === true || currentParams[param.name] === '1' || currentParams[param.name] === 1;
                    field.innerHTML = `
                        <div class="form-check">
                            <input type="checkbox" 
                                   name="check_params[${param.name}]"
                                   class="form-check-input"
                                   id="check_params_${param.name}"
                                   value="1"
                                   ${checked ? 'checked' : ''}>
                            <label class="form-check-label" for="check_params_${param.name}">${param.label}</label>
                        </div>
                    `;
                } else {
                    field.innerHTML = `
                        <label class="form-label">${param.label}</label>
                        <input type="${param.type}" 
                               name="check_params[${param.name}]"
                               class="form-control"
                               value="${currentParams[param.name] || ''}">
                    `;
                }
                checkParamsContent.appendChild(field);
            });
        } else if (checkTypeValue === '<?= TaskV2::CHECK_TYPE_DAILY_REWARD ?>') {
            // Интерфейс для настройки ежедневных наград
            renderDailyRewardsInterface();
        }
    }
    
    // Функция для отображения интерфейса ежедневных наград
    function renderDailyRewardsInterface() {
        const currentParams = <?= Json::encode($model->check_params ?? []) ?>;
        const rewards = currentParams.rewards || [];
        
        if (rewards.length === 0) {
            rewards.push({drop_id: 843, amount: 10}); // По умолчанию первая награда - 10 монет
        }
        
        const container = document.createElement('div');
        container.className = 'daily-rewards-container';
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <?= Html::encode(Yii::t('common', 'Настройте список наград для ежедневного задания. Каждый день пользователь будет получать следующую награду из списка. При пропуске дня последовательность сбрасывается на первую награду.')) ?>
            </div>
            <div id="daily-rewards-list"></div>
            <button type="button" class="btn btn-sm btn-success mt-2" id="add-daily-reward">
                <i class="fas fa-plus"></i> <?= Html::encode(Yii::t('common', 'Добавить награду')) ?>
            </button>
        `;
        checkParamsContent.appendChild(container);
        
        // Рендерим список наград
        function renderRewardsList() {
            const listContainer = document.getElementById('daily-rewards-list');
            listContainer.innerHTML = '';
            
            rewards.forEach(function(reward, index) {
                const rewardRow = document.createElement('div');
                rewardRow.className = 'daily-reward-row mb-3 p-3 border rounded';
                rewardRow.dataset.index = index;
                
                const isCurrency = reward.drop_id == 843;
                
                rewardRow.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong><?= Html::encode(Yii::t('common', 'День {day}', ['day' => ''])) ?><span class="reward-day-number">${index + 1}</span></strong>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-daily-reward" ${rewards.length <= 1 ? 'style="display:none;"' : ''}>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label"><?= Html::encode(Yii::t('common', 'Тип награды')) ?></label>
                            <select name="check_params[rewards][${index}][reward_type]" class="form-control reward-type-select" data-index="${index}">
                                <option value="currency" ${isCurrency ? 'selected' : ''}><?= Html::encode(Yii::t('common', 'Валюта (монеты)')) ?></option>
                                <option value="item" ${!isCurrency ? 'selected' : ''}><?= Html::encode(Yii::t('common', 'Предмет')) ?></option>
                            </select>
                        </div>
                        <div class="col-md-6 reward-amount-container">
                            <label class="form-label"><?= Html::encode(Yii::t('common', 'Количество')) ?></label>
                            <input type="number" 
                                   name="check_params[rewards][${index}][amount]" 
                                   class="form-control" 
                                   value="${reward.amount || 1}" 
                                   min="1" 
                                   step="1"
                                   required>
                        </div>
                        <div class="col-md-12 reward-item-container" style="display: ${!isCurrency ? 'block' : 'none'};">
                            <label class="form-label"><?= Html::encode(Yii::t('common', 'Предмет')) ?></label>
                            <select name="check_params[rewards][${index}][drop_id]" 
                                    class="form-control reward-item-select" 
                                    data-index="${index}"
                                    ${isCurrency ? 'style="display:none;"' : ''}>
                                <option value=""><?= Html::encode(Yii::t('common', 'Выберите предмет...')) ?></option>
                            </select>
                            <input type="hidden" name="check_params[rewards][${index}][drop_id]" value="${isCurrency ? 843 : (reward.drop_id || '')}" class="reward-drop-id-input" data-index="${index}">
                        </div>
                    </div>
                `;
                listContainer.appendChild(rewardRow);
                
                // Инициализируем Select2 для выбора предмета, если это не валюта
                if (!isCurrency && reward.drop_id) {
                    setTimeout(function() {
                        initRewardItemSelect(index, reward.drop_id);
                    }, 100);
                }
            });
            
            // Обновляем видимость кнопок удаления
            updateRemoveRewardButtons();
        }
        
        // Инициализация Select2 для выбора предмета
        function initRewardItemSelect(index, selectedDropId) {
            const select = document.querySelector(`.reward-item-select[data-index="${index}"]`);
            if (!select) return;
            
            // Используем существующий Select2, если он уже инициализирован
            if ($(select).hasClass('select2-hidden-accessible')) {
                $(select).select2('destroy');
            }
            
            $(select).select2({
                ajax: {
                    url: '<?= Url::to(['drop/list-json']) ?>',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            page: params.page
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || []
                        };
                    },
                    cache: true
                },
                templateResult: <?= $dropFormat ?>,
                templateSelection: <?= $dropFormat ?>,
                escapeMarkup: function(m) { return m; },
                placeholder: '<?= Html::encode(Yii::t('common', 'Выберите предмет...')) ?>',
                allowClear: true,
                minimumInputLength: 0
            });
            
            // Устанавливаем выбранное значение, если есть
            if (selectedDropId && selectedDropId != 843) {
                // Загружаем данные предмета
                $.ajax({
                    url: '<?= Url::to(['drop/get-json']) ?>',
                    data: {id: selectedDropId},
                    dataType: 'json',
                    success: function(data) {
                        if (data && data.id) {
                            const option = new Option(data.text, data.id, true, true);
                            $(select).append(option).trigger('change');
                        }
                    }
                });
            }
            
            // Обработчик изменения выбранного предмета
            $(select).on('select2:select select2:clear', function(e) {
                const dropIdInput = document.querySelector(`.reward-drop-id-input[data-index="${index}"]`);
                if (dropIdInput) {
                    dropIdInput.value = e.params && e.params.data ? e.params.data.id : '';
                }
            });
        }
        
        // Обновление видимости кнопок удаления
        function updateRemoveRewardButtons() {
            const removeButtons = document.querySelectorAll('.remove-daily-reward');
            removeButtons.forEach(function(btn) {
                btn.style.display = rewards.length > 1 ? 'block' : 'none';
            });
        }
        
        // Обработчик изменения типа награды
        checkParamsContent.addEventListener('change', function(e) {
            if (e.target.classList.contains('reward-type-select')) {
                const index = parseInt(e.target.dataset.index);
                const rewardRow = e.target.closest('.daily-reward-row');
                const itemContainer = rewardRow.querySelector('.reward-item-container');
                const dropIdInput = rewardRow.querySelector('.reward-drop-id-input');
                
                if (e.target.value === 'currency') {
                    itemContainer.style.display = 'none';
                    if (dropIdInput) {
                        dropIdInput.value = 843; // ID для валюты
                    }
                } else {
                    itemContainer.style.display = 'block';
                    const select = rewardRow.querySelector('.reward-item-select');
                    if (select && !$(select).hasClass('select2-hidden-accessible')) {
                        initRewardItemSelect(index, null);
                    }
                }
            }
        });
        
        // Обработчик добавления награды
        document.getElementById('add-daily-reward').addEventListener('click', function() {
            rewards.push({drop_id: 843, amount: 10});
            renderRewardsList();
        });
        
        // Обработчик удаления награды
        checkParamsContent.addEventListener('click', function(e) {
            if (e.target.closest('.remove-daily-reward')) {
                const row = e.target.closest('.daily-reward-row');
                const index = parseInt(row.dataset.index);
                rewards.splice(index, 1);
                renderRewardsList();
            }
        });
        
        // Инициализация списка
        renderRewardsList();
    }
    
    // Обработчики событий
    taskType.addEventListener('change', function() {
        updatePerUserLimit();
        updateMaxProgress();
    });
    checkType.addEventListener('change', function() {
        updateCheckParams();
        updateMaxProgress();
    });
    rewardType.addEventListener('change', updateRewardFields);
    
    // Инициализация при загрузке
    updatePerUserLimit();
    updateMaxProgress();
    updateRewardFields();
    updateCheckParams();
    
    // Добавление/удаление дополнительных кнопок
    let extraButtonIndex = <?= count($model->extra_buttons ?? []) ?>;
    document.getElementById('add-extra-button').addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'extra-button-row mb-2';
        row.dataset.index = extraButtonIndex;
        row.innerHTML = `
            <div class="input-group">
                <input type="text" name="extra_buttons[${extraButtonIndex}][label]" 
                       class="form-control" 
                       placeholder="<?= Yii::t('common', 'Название кнопки') ?>">
                <input type="text" name="extra_buttons[${extraButtonIndex}][url]" 
                       class="form-control" 
                       placeholder="<?= Yii::t('common', 'URL') ?>">
                <button type="button" class="btn btn-outline-danger remove-extra-button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        extraButtonsContainer.insertBefore(row, this);
        extraButtonIndex++;
        updateRemoveButtons();
    });
    
    function updateRemoveButtons() {
        const rows = extraButtonsContainer.querySelectorAll('.extra-button-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-extra-button');
            if (removeBtn) {
                removeBtn.style.display = rows.length > 1 ? 'block' : 'none';
            }
        });
    }
    
    extraButtonsContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-extra-button')) {
            const row = e.target.closest('.extra-button-row');
            if (extraButtonsContainer.querySelectorAll('.extra-button-row').length > 1) {
                row.remove();
                updateRemoveButtons();
            }
        }
    });
    
    updateRemoveButtons();
});
</script>

<style>
.drop-select-item {
    padding: 5px;
    background: #f1f1f1;
    border-radius: 5px;
    text-align: center;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #000;
    justify-content: flex-start;
}
.drop-select-item img {
    display: block;
    width: 24px;
}
</style>

