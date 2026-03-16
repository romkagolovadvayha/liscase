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

$fieldOpt = ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{hint}{error}'];
$labelOpt = ['class' => 'text-xs text-gray-400 mb-1 block'];
?>
<div class="tasks-v2-form-wrap">
    <?php $form = ActiveForm::begin([
        'id' => 'tasks-v2-form',
        'options' => ['enctype' => 'multipart/form-data', 'class' => 'tasks-v2-form'],
        'fieldConfig' => [
            'labelOptions' => $labelOpt,
            'inputOptions' => ['class' => 'ds-input w-full text-sm'],
        ],
    ]); ?>
    <div class="tasks-v2-form-layout">
    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 tasks-v2-form-content">
        <?= $form->field($model, 'title', $fieldOpt)->textInput(['maxlength' => true, 'class' => 'ds-input w-full text-sm']) ?>

        <?= $form->field($model, 'short_description', $fieldOpt)->textarea(['rows' => 2, 'class' => 'ds-input w-full text-sm']) ?>

        <?= $form->field($model, 'full_description', $fieldOpt)->textarea(['rows' => 6, 'class' => 'ds-input w-full text-sm']) ?>

        <div class="flex flex-nowrap gap-3 mb-2">
            <div class="flex-1 min-w-0">
                <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('type') ?></label>
                <div class="ds-select-wrapper">
                    <?= Html::activeDropDownList($model, 'type', TaskV2::getTypeList(), [
                        'id' => 'task-type',
                        'prompt' => Yii::t('common', 'Выберите тип...'),
                        'class' => 'ds-select w-full text-sm',
                    ]) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('check_type') ?></label>
                <div class="ds-select-wrapper">
                    <?= Html::activeDropDownList($model, 'check_type', TaskV2::getCheckTypeList(), [
                        'id' => 'task-check-type',
                        'prompt' => Yii::t('common', 'Выберите тип проверки...'),
                        'class' => 'ds-select w-full text-sm',
                    ]) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
        </div>

        <div id="check-params-container" class="mb-4" style="display: none;">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры проверки') ?></h3>
            <div id="check-params-content" class="space-y-3"></div>
        </div>

        <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Награда') ?></h3>
        <div class="flex flex-wrap gap-3 mb-2">
            <div class="min-w-0" style="flex: 1 1 200px;">
                <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('reward_type') ?></label>
                <div class="ds-select-wrapper">
                    <?= $form->field($model, 'reward_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(TaskV2::getRewardTypeList(), [
                        'id' => 'task-reward-type',
                        'prompt' => Yii::t('common', 'Выберите тип награды...'),
                        'class' => 'ds-select w-full text-sm',
                    ]) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
            <div class="min-w-0" id="reward-item-container" style="display: none; flex: 1 1 200px;">
                <?= $form->field($model, 'reward_item_id', $fieldOpt)->widget(Select2::class, [
                    'data' => Drop::getList(),
                    'options' => ['placeholder' => Yii::t('common', 'Выберите товар...'), 'class' => 'ds-select w-full text-sm'],
                    'pluginOptions' => [
                        'templateResult' => $dropFormat,
                        'templateSelection' => $dropFormat,
                        'escapeMarkup' => new JsExpression('function(m){return m}'),
                        'allowClear' => true,
                    ],
                ]) ?>
            </div>
            <div class="min-w-0" id="reward-currency-container" style="display: none; flex: 1 1 200px;">
                <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('reward_currency') ?></label>
                <div class="ds-select-wrapper">
                    <?= $form->field($model, 'reward_currency', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList([
                        'personal' => Yii::t('common', 'Лицевой счет'),
                        'skins' => Yii::t('common', 'Скины'),
                    ], ['prompt' => Yii::t('common', 'Выберите тип баланса...'), 'class' => 'ds-select w-full text-sm']) ?>
                    <i class="fas fa-chevron-down ds-select-arrow"></i>
                </div>
            </div>
            <div class="min-w-0" id="reward-amount-container" style="display: none; flex: 1 1 120px;">
                <?= $form->field($model, 'reward_amount', $fieldOpt)->textInput(['type' => 'number', 'step' => '0.01', 'class' => 'ds-input w-full text-sm']) ?>
            </div>
        </div>

        <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Лимиты') ?></h3>
        <div class="flex flex-wrap gap-3 mb-2">
            <div class="min-w-0" id="per-user-limit-container" style="display: none; flex: 1 1 140px;">
                <?= $form->field($model, 'per_user_limit', $fieldOpt)->textInput(['type' => 'number', 'class' => 'ds-input w-full text-sm'])
                    ->hint(Yii::t('common', 'Пусто = неограниченно на пользователя')) ?>
            </div>
            <div class="min-w-0" style="flex: 1 1 140px;">
                <?= $form->field($model, 'global_limit', $fieldOpt)->textInput(['type' => 'number', 'class' => 'ds-input w-full text-sm'])
                    ->hint(Yii::t('common', 'Пусто = неограниченно всего')) ?>
            </div>
            <div class="min-w-0" id="max-progress-container" style="display: none; flex: 1 1 140px;">
                <?= $form->field($model, 'max_progress', $fieldOpt)->textInput(['type' => 'number', 'min' => 1, 'class' => 'ds-input w-full text-sm'])
                    ->hint(Yii::t('common', 'Макс. прогресс (для отображения)')) ?>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a(Yii::t('common', 'Отмена'), ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </div>

    <!-- Правая колонка: изображение, UX, видимость (на всю высоту как в форме предмета) -->
    <aside class="tasks-v2-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto flex flex-col">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Изображение') ?></h3>
                <?php if ($model->image_path): ?>
                    <div class="mb-3">
                        <img src="/<?= Html::encode(ltrim($model->image_path, '/')) ?>" alt="<?= Html::encode($model->title) ?>" class="w-full rounded object-cover max-h-32" />
                        <?= Html::a('<i class="fas fa-trash"></i> ' . Yii::t('common', 'Удалить'), ['delete-image', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm mt-2', 'data-confirm' => Yii::t('common', 'Удалить изображение?'), 'data-method' => 'post', 'style' => 'display: none;']) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('imageFile') ?></label>
                    <?= Html::activeFileInput($model, 'imageFile', ['accept' => 'image/*', 'class' => 'ds-input w-full text-sm']) ?>
                    <p class="text-xs text-gray-500 mt-1"><?= Yii::t('common', 'Рекомендуемый размер: 400x300px или 16:9') ?></p>
                </div>
            </div>
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'UX') ?></h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('button_text') ?></label>
                        <?= Html::activeTextInput($model, 'button_text', ['class' => 'ds-input w-full text-sm', 'maxlength' => true]) ?>
                        <p class="text-xs text-gray-500 mt-1"><?= Yii::t('common', 'По умолчанию: "Проверить"') ?></p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Доп. кнопки-ссылки') ?></span>
                        <div id="extra-buttons-container" class="space-y-2">
                            <?php
                            $extraButtons = $model->extra_buttons ?? [];
                            if (empty($extraButtons)) {
                                $extraButtons = [['label' => '', 'url' => '']];
                            }
                            foreach ($extraButtons as $index => $button): ?>
                                <div class="extra-button-row flex flex-wrap gap-2 items-center" data-index="<?= $index ?>">
                                    <?= Html::textInput("extra_buttons[{$index}][label]", $button['label'] ?? '', ['class' => 'ds-input flex-1 min-w-0 text-sm', 'placeholder' => Yii::t('common', 'Название')]) ?>
                                    <?= Html::textInput("extra_buttons[{$index}][url]", $button['url'] ?? '', ['class' => 'ds-input flex-1 min-w-0 text-sm', 'placeholder' => 'URL']) ?>
                                    <button type="button" class="ds-btn ds-btn--danger ds-btn--icon remove-extra-button" <?= count($extraButtons) <= 1 ? 'style="display:none;"' : '' ?>><i class="fas fa-times"></i></button>
                                </div>
                            <?php endforeach; ?>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" id="add-extra-button"><i class="fas fa-plus"></i> <?= Yii::t('common', 'Добавить кнопку') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Видимость') ?></h3>
                <div class="space-y-3">
                    <div>
                        <?= $form->field($model, 'is_visible_for_guests', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->checkbox(['label' => Yii::t('common', 'Показывать гостям'), 'value' => 1, 'uncheck' => 0]) ?>
                        <p class="text-xs text-gray-500 mt-0"><?= Yii::t('common', 'Не смогут выполнить') ?></p>
                    </div>
                    <div>
                        <?= $form->field($model, 'is_vip_only', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->checkbox(['label' => Yii::t('common', 'Только для VIP'), 'value' => 1, 'uncheck' => 0]) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('available_from') ?></label>
                        <?= Html::activeInput('datetime-local', $model, 'available_from', [
                            'value' => $model->available_from ? date('Y-m-d\TH:i', strtotime($model->available_from)) : '',
                            'class' => 'ds-input w-full text-sm',
                        ]) ?>
                    </div>
                    <div>
                        <?= $form->field($model, 'is_active', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->checkbox(['label' => Yii::t('common', 'Активно'), 'value' => 1, 'uncheck' => 0]) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('sort') ?></label>
                        <?= Html::activeTextInput($model, 'sort', ['type' => 'number', 'class' => 'ds-input w-full text-sm']) ?>
                        <p class="text-xs text-gray-500 mt-1"><?= Yii::t('common', 'Меньше = выше в списке') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </aside>
    </div><!-- /.tasks-v2-form-layout -->

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
            '<?= TaskV2::CHECK_TYPE_SKIN_ADD ?>': [
                {name: 'count', label: '<?= Html::encode(Yii::t('common', 'Требуемое количество одобренных скинов')) ?>', type: 'number'}
            ],
            '<?= TaskV2::CHECK_TYPE_COBALTLAB_REGISTRATION ?>': [
                {name: 'token', label: '<?= Html::encode(Yii::t('common', 'Affiliate-токен CobaltLab')) ?>', type: 'text'}
            ],
            '<?= TaskV2::CHECK_TYPE_COBALTLAB_FIRST_DEPOSIT ?>': [
                {name: 'token', label: '<?= Html::encode(Yii::t('common', 'Affiliate-токен CobaltLab')) ?>', type: 'text'}
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
        row.className = 'extra-button-row flex flex-wrap gap-2 items-center';
        row.dataset.index = extraButtonIndex;
        row.innerHTML = `
            <input type="text" name="extra_buttons[${extraButtonIndex}][label]" class="ds-input flex-1 min-w-0 text-sm" placeholder="<?= Yii::t('common', 'Название') ?>">
            <input type="text" name="extra_buttons[${extraButtonIndex}][url]" class="ds-input flex-1 min-w-0 text-sm" placeholder="URL">
            <button type="button" class="ds-btn ds-btn--danger ds-btn--icon remove-extra-button"><i class="fas fa-times"></i></button>
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
.tasks-v2-form-wrap .ds-select-wrapper { position: relative; }
.tasks-v2-form-wrap .ds-select-wrapper .ds-select-arrow { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: hsl(0 0% 55%); font-size: 12px; }
#check-params-content .mb-3 { margin-bottom: 0.75rem; }
#check-params-content .form-label { font-size: 12px; color: hsl(0 0% 65%); margin-bottom: 4px; display: block; }
#check-params-content .form-check { display: flex; align-items: center; gap: 0.5rem; }
#check-params-content .form-check-label { font-size: 13px; color: hsl(0 0% 85%); }
.daily-rewards-container .alert-info {
    background: hsl(220 30% 18% / 1);
    border: 1px solid hsl(220 25% 28% / 1);
    color: hsl(0 0% 85%);
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-size: 13px;
}
.daily-reward-row {
    background: hsl(0 0% 16% / 1);
    border: 1px solid hsl(0 0% 22% / 1);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 0.75rem;
}
.daily-reward-row .form-label { font-size: 12px; color: hsl(0 0% 65%); margin-bottom: 4px; display: block; }
.drop-select-item {
    padding: 5px;
    background: hsl(0 0% 18% / 1);
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #e5e5e5;
}
.drop-select-item img { display: block; width: 24px; }
</style>

