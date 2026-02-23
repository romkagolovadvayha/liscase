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

$fieldTemplate = '<div class="tasks-v2-form__field mb-4">{label}{input}{hint}{error}</div>';
$labelClass = 'text-xs text-gray-400 mb-1 block';
?>
<div class="tasks-v2-form-wrap">
    <?php $form = ActiveForm::begin([
        'id' => 'tasks-v2-form',
        'options' => ['enctype' => 'multipart/form-data', 'class' => 'tasks-v2-form'],
        'fieldConfig' => [
            'template' => $fieldTemplate,
            'labelOptions' => ['class' => $labelClass],
            'inputOptions' => ['class' => 'ds-input w-full'],
            'options' => ['class' => 'mb-0'],
        ],
    ]); ?>

    <div class="tasks-v2-form-section">
        <div class="tasks-v2-form-section__header">
            <h4 class="tasks-v2-form-section__title"><?= Html::encode($this->title) ?></h4>
        </div>
        <div class="tasks-v2-form-section__body">
            <div class="tasks-v2-form__grid">
                <div class="tasks-v2-form__main">
                    <?= $form->field($model, 'title')->textInput(['maxlength' => true, 'class' => 'ds-input w-full']) ?>
                    
                    <?= $form->field($model, 'short_description')->textarea(['rows' => 2, 'class' => 'ds-input w-full']) ?>
                    
                    <?= $form->field($model, 'full_description')->textarea(['rows' => 6, 'class' => 'ds-input w-full']) ?>
                    
                    <div class="tasks-v2-form__row">
                        <div class="tasks-v2-form__col">
                            <?= $form->field($model, 'type')->dropDownList(TaskV2::getTypeList(), [
                                'id' => 'task-type',
                                'prompt' => Yii::t('common', 'Выберите тип...'),
                                'class' => 'ds-select w-full',
                            ]) ?>
                        </div>
                        <div class="tasks-v2-form__col">
                            <?= $form->field($model, 'check_type')->dropDownList(TaskV2::getCheckTypeList(), [
                                'id' => 'task-check-type',
                                'prompt' => Yii::t('common', 'Выберите тип проверки...'),
                                'class' => 'ds-select w-full',
                            ]) ?>
                        </div>
                    </div>
                    
                    <div id="check-params-container" class="tasks-v2-form__block" style="display: none;">
                        <h5 class="tasks-v2-form__subtitle"><?= Yii::t('common', 'Параметры проверки') ?></h5>
                        <div id="check-params-content"></div>
                    </div>
                    
                    <h5 class="tasks-v2-form__subtitle"><?= Yii::t('common', 'Награда') ?></h5>
                    <div class="tasks-v2-form__row">
                        <div class="tasks-v2-form__col">
                            <?= $form->field($model, 'reward_type')->dropDownList(TaskV2::getRewardTypeList(), [
                                'id' => 'task-reward-type',
                                'prompt' => Yii::t('common', 'Выберите тип награды...'),
                                'class' => 'ds-select w-full',
                            ]) ?>
                        </div>
                        <div class="tasks-v2-form__col" id="reward-item-container" style="display: none;">
                            <?= $form->field($model, 'reward_item_id')->widget(Select2::class, [
                                'data' => Drop::getList(),
                                'options' => [
                                    'placeholder' => Yii::t('common', 'Выберите товар...'),
                                    'class' => 'ds-select w-full',
                                ],
                                'pluginOptions' => [
                                    'templateResult' => $dropFormat,
                                    'templateSelection' => $dropFormat,
                                    'escapeMarkup' => new JsExpression('function(m){return m}'),
                                    'allowClear' => true,
                                ],
                            ]) ?>
                        </div>
                        <div class="tasks-v2-form__col" id="reward-currency-container" style="display: none;">
                            <?= $form->field($model, 'reward_currency')->dropDownList([
                                'personal' => Yii::t('common', 'Лицевой счет'),
                                'skins' => Yii::t('common', 'Скины'),
                            ], ['prompt' => Yii::t('common', 'Выберите тип баланса...'), 'class' => 'ds-select w-full']) ?>
                        </div>
                        <div class="tasks-v2-form__col" id="reward-amount-container" style="display: none;">
                            <?= $form->field($model, 'reward_amount')->textInput(['type' => 'number', 'step' => '0.01', 'class' => 'ds-input w-full']) ?>
                        </div>
                    </div>
                    
                    <h5 class="tasks-v2-form__subtitle"><?= Yii::t('common', 'Лимиты') ?></h5>
                    <div class="tasks-v2-form__row">
                        <div class="tasks-v2-form__col" id="per-user-limit-container" style="display: none;">
                            <?= $form->field($model, 'per_user_limit')->textInput(['type' => 'number', 'class' => 'ds-input w-full'])
                                ->hint(Yii::t('common', 'Оставьте пустым для неограниченного количества выполнений на пользователя')) ?>
                        </div>
                        <div class="tasks-v2-form__col">
                            <?= $form->field($model, 'global_limit')->textInput(['type' => 'number', 'class' => 'ds-input w-full'])
                                ->hint(Yii::t('common', 'Оставьте пустым для неограниченного общего количества выполнений')) ?>
                        </div>
                        <div class="tasks-v2-form__col" id="max-progress-container" style="display: none;">
                            <?= $form->field($model, 'max_progress')->textInput(['type' => 'number', 'min' => 1, 'class' => 'ds-input w-full'])
                                ->hint(Yii::t('common', 'Максимальный прогресс для отображения (для заданий с прогрессом). Оставьте пустым, если прогресс вычисляется автоматически.')) ?>
                        </div>
                    </div>
                </div>
                
                <div class="tasks-v2-form__sidebar">
                    <h5 class="tasks-v2-form__subtitle"><?= Yii::t('common', 'Изображение') ?></h5>
                    <?php if ($model->image_path): ?>
                        <div class="tasks-v2-form__image-preview mb-4">
                            <img src="/<?= Html::encode(ltrim($model->image_path, '/')) ?>"
                                 alt="<?= Html::encode($model->title) ?>"
                                 class="tasks-v2-form__image">
                            <br>
                            <?= Html::a(
                                '<i class="fas fa-trash"></i> ' . Yii::t('common', 'Удалить изображение'),
                                ['delete-image', 'id' => $model->id],
                                [
                                    'class' => 'ds-btn ds-btn--danger ds-btn--sm mt-2',
                                    'data-confirm' => Yii::t('common', 'Вы уверены, что хотите удалить изображение?'),
                                    'data-method' => 'post',
                                    'style' => 'display: none;',
                                ]
                            ) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?= $form->field($model, 'imageFile')->fileInput(['accept' => 'image/*', 'class' => 'ds-input w-full'])
                        ->hint(Yii::t('common', 'Рекомендуемый размер: 400x300px или 16:9')) ?>
                    
                    <h5 class="tasks-v2-form__subtitle"><?= Yii::t('common', 'UX-настройки') ?></h5>
                    <?= $form->field($model, 'button_text')->textInput(['maxlength' => true, 'class' => 'ds-input w-full'])
                        ->hint(Yii::t('common', 'По умолчанию: "Проверить"')) ?>
                    
                    <h6 class="tasks-v2-form__subtitle-sm"><?= Yii::t('common', 'Дополнительные кнопки-ссылки') ?></h6>
                    <div id="extra-buttons-container">
                        <?php
                        $extraButtons = $model->extra_buttons ?? [];
                        if (empty($extraButtons)) {
                            $extraButtons = [['label' => '', 'url' => '']];
                        }
                        foreach ($extraButtons as $index => $button): ?>
                            <div class="extra-button-row tasks-v2-form__extra-row" data-index="<?= $index ?>">
                                <div class="tasks-v2-form__extra-group">
                                    <?= Html::textInput("extra_buttons[{$index}][label]", $button['label'] ?? '', [
                                        'class' => 'ds-input flex-1 min-w-0',
                                        'placeholder' => Yii::t('common', 'Название кнопки')
                                    ]) ?>
                                    <?= Html::textInput("extra_buttons[{$index}][url]", $button['url'] ?? '', [
                                        'class' => 'ds-input flex-1 min-w-0',
                                        'placeholder' => Yii::t('common', 'URL')
                                    ]) ?>
                                    <?php if (count($extraButtons) > 1): ?>
                                    <button type="button" class="ds-btn ds-btn--danger ds-btn--icon remove-extra-button">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="ds-btn ds-btn--danger ds-btn--icon remove-extra-button" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" id="add-extra-button">
                            <i class="fas fa-plus"></i> <?= Yii::t('common', 'Добавить кнопку') ?>
                        </button>
                    </div>
                    
                    <h5 class="tasks-v2-form__subtitle"><?= Yii::t('common', 'Видимость') ?></h5>
                    <?= $form->field($model, 'is_visible_for_guests')->checkbox([
                        'template' => '<div class="tasks-v2-form__checkbox-wrap mb-4">{input}{label}</div>{hint}{error}',
                        'labelOptions' => ['class' => 'tasks-v2-form__checkbox-label'],
                    ])->hint(Yii::t('common', 'Отображать задание для неавторизованных пользователей (не смогут выполнить)')) ?>
                    
                    <?= $form->field($model, 'is_vip_only')->checkbox([
                        'template' => '<div class="tasks-v2-form__checkbox-wrap mb-4">{input}{label}</div>{hint}{error}',
                        'labelOptions' => ['class' => 'tasks-v2-form__checkbox-label'],
                    ])->hint(Yii::t('common', 'Задание доступно только для пользователей со статусом VIP')) ?>
                    
                    <?= $form->field($model, 'available_from')->textInput([
                            'type' => 'datetime-local',
                            'value' => $model->available_from ? date('Y-m-d\TH:i', strtotime($model->available_from)) : '',
                            'class' => 'ds-input w-full',
                        ])
                        ->hint(Yii::t('common', 'Дата и время, когда задание станет доступно. Оставьте пустым, если задание доступно сразу.')) ?>
                    
                    <?= $form->field($model, 'is_active')->checkbox([
                        'template' => '<div class="tasks-v2-form__checkbox-wrap mb-4">{input}{label}</div>{hint}{error}',
                        'labelOptions' => ['class' => 'tasks-v2-form__checkbox-label'],
                    ]) ?>
                    
                    <?= $form->field($model, 'sort')->textInput(['type' => 'number', 'class' => 'ds-input w-full'])
                        ->hint(Yii::t('common', 'Меньше значение = выше в списке')) ?>
                </div>
            </div>
        </div>
        <div class="tasks-v2-form-section__footer">
            <?= Html::submitButton(
                Yii::t('common', 'Сохранить'),
                ['class' => 'ds-btn ds-btn--primary']
            ) ?>
            <?= Html::a(
                Yii::t('common', 'Отмена'),
                ['index'],
                ['class' => 'ds-btn ds-btn--secondary']
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
            '<?= TaskV2::CHECK_TYPE_TELEGRAM_CHANNEL_SUBSCRIBE ?>': [
                {name: 'chat_id', label: '<?= Html::encode(Yii::t('common', 'ID канала Telegram (например: -1001234567890)')) ?>', type: 'text'},
                {name: 'channel_username', label: '<?= Html::encode(Yii::t('common', 'Username канала (например: @channelname, опционально)')) ?>', type: 'text'}
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
                               class="ds-input w-full"
                               value="${(currentParams[param.name] || '').toString().replace(/"/g, '&quot;')}">
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
            <button type="button" class="ds-btn ds-btn--primary ds-btn--sm mt-2" id="add-daily-reward">
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
                        <strong class="text-gray-300"><?= Html::encode(Yii::t('common', 'День {day}', ['day' => ''])) ?><span class="reward-day-number">${index + 1}</span></strong>
                        <button type="button" class="ds-btn ds-btn--danger ds-btn--sm remove-daily-reward" ${rewards.length <= 1 ? 'style="display:none;"' : ''}>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label"><?= Html::encode(Yii::t('common', 'Тип награды')) ?></label>
                            <select name="check_params[rewards][${index}][reward_type]" class="ds-select w-full reward-type-select" data-index="${index}">
                                <option value="currency" ${isCurrency ? 'selected' : ''}><?= Html::encode(Yii::t('common', 'Валюта (монеты)')) ?></option>
                                <option value="item" ${!isCurrency ? 'selected' : ''}><?= Html::encode(Yii::t('common', 'Предмет')) ?></option>
                            </select>
                        </div>
                        <div class="col-md-6 reward-amount-container">
                            <label class="form-label"><?= Html::encode(Yii::t('common', 'Количество')) ?></label>
                            <input type="number"
                                   name="check_params[rewards][${index}][amount]"
                                   class="ds-input w-full"
                                   value="${reward.amount || 1}"
                                   min="1"
                                   step="1"
                                   required>
                        </div>
                        <div class="col-md-12 reward-item-container" style="display: ${!isCurrency ? 'block' : 'none'};">
                            <label class="form-label"><?= Html::encode(Yii::t('common', 'Предмет')) ?></label>
                            <select name="check_params[rewards][${index}][drop_id]"
                                    class="ds-select w-full reward-item-select"
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
            <div class="tasks-v2-form__extra-group">
                <input type="text" name="extra_buttons[${extraButtonIndex}][label]"
                       class="ds-input flex-1 min-w-0"
                       placeholder="<?= Yii::t('common', 'Название кнопки') ?>">
                <input type="text" name="extra_buttons[${extraButtonIndex}][url]"
                       class="ds-input flex-1 min-w-0"
                       placeholder="<?= Yii::t('common', 'URL') ?>">
                <button type="button" class="ds-btn ds-btn--danger ds-btn--icon remove-extra-button">
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
.tasks-v2-form-wrap { padding: 0 1rem 1rem; }
.tasks-v2-form-section {
    background: hsl(0 0% 20.4% / 1);
    border: 1px solid hsl(0 0% 15.3% / 1);
    border-radius: 8px;
    overflow: hidden;
}
.tasks-v2-form-section__header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid hsl(0 0% 15.3% / 1);
}
.tasks-v2-form-section__title { margin: 0; font-size: 1.125rem; font-weight: 600; color: #fff; }
.tasks-v2-form-section__body { padding: 1.25rem; }
.tasks-v2-form-section__footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid hsl(0 0% 15.3% / 1);
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.tasks-v2-form__grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 2rem;
}
@media (max-width: 991px) {
    .tasks-v2-form__grid { grid-template-columns: 1fr; }
}
.tasks-v2-form__main { min-width: 0; }
.tasks-v2-form__sidebar { min-width: 0; }
.tasks-v2-form__row { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
.tasks-v2-form__col { flex: 1 1 200px; min-width: 0; }
.tasks-v2-form__block { margin-bottom: 1rem; }
.tasks-v2-form__subtitle { margin: 1.25rem 0 0.75rem; font-size: 0.9375rem; font-weight: 600; color: hsl(0 0% 85%); }
.tasks-v2-form__subtitle:first-child { margin-top: 0; }
.tasks-v2-form__subtitle-sm { margin: 0.75rem 0 0.5rem; font-size: 0.875rem; font-weight: 500; color: hsl(0 0% 75%); }
.tasks-v2-form__image { max-width: 100%; height: auto; border-radius: 8px; display: block; }
.tasks-v2-form__extra-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.tasks-v2-form__extra-group .ds-input { min-width: 120px; }
.tasks-v2-form__checkbox-wrap { display: flex; align-items: flex-start; gap: 0.5rem; }
.tasks-v2-form__checkbox-wrap input[type="checkbox"] { margin-top: 0.2rem; }
.tasks-v2-form__checkbox-label { font-size: 0.875rem; color: hsl(0 0% 80%); cursor: pointer; }
.tasks-v2-form .help-block { font-size: 0.75rem; color: hsl(0 0% 60%); margin-top: 0.25rem; }
.tasks-v2-form .has-error .help-block { color: #f87171; }

/* check-params (динамические поля) */
#check-params-content .mb-3 { margin-bottom: 1rem; }
#check-params-content .form-label,
.tasks-v2-form .daily-rewards-container .form-label { font-size: 0.75rem; color: hsl(0 0% 65%); margin-bottom: 0.25rem; display: block; }
#check-params-content .form-control,
.tasks-v2-form .daily-reward-row .form-control { background: hsl(0 0% 14% / 1); border-color: hsl(0 0% 22% / 1); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; width: 100%; }
#check-params-content .form-check { display: flex; align-items: center; gap: 0.5rem; }
#check-params-content .form-check-input { margin: 0; }
#check-params-content .form-check-label { font-size: 0.875rem; color: hsl(0 0% 80%); }
.tasks-v2-form .daily-rewards-container .alert-info {
    background: hsl(220 30% 18% / 1);
    border: 1px solid hsl(220 25% 28% / 1);
    color: hsl(0 0% 85%);
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
}
.tasks-v2-form .daily-reward-row {
    background: hsl(0 0% 16% / 1);
    border: 1px solid hsl(0 0% 22% / 1);
    border-radius: 6px;
    padding: 1rem;
}
.tasks-v2-form .daily-reward-row .reward-type-select,
.tasks-v2-form .daily-reward-row select.reward-item-select { background: hsl(0 0% 14% / 1); border-color: hsl(0 0% 22% / 1); color: #fff; padding: 0.5rem 0.75rem; border-radius: 6px; }
.tasks-v2-form .daily-reward-row .btn { padding: 0.35rem 0.65rem; font-size: 0.8125rem; border-radius: 6px; }
.tasks-v2-form .daily-reward-row .btn-outline-danger { color: #f87171; border-color: #f87171; }
.tasks-v2-form .daily-reward-row .btn-success { background: hsl(142 70% 35%); color: #fff; border: none; }

.drop-select-item {
    padding: 5px;
    background: hsl(0 0% 18% / 1);
    border-radius: 5px;
    text-align: center;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #e5e5e5;
    justify-content: flex-start;
}
.drop-select-item img { display: block; width: 24px; }
</style>

