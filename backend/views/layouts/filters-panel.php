<?php

use backend\models\ClanSearch;
use backend\models\ServersTagsSearch;
use common\models\box\Select;
use common\models\box\SelectSearch;
use common\models\clan\Clan;
use common\models\servers\Servers;
use common\models\servers\ServersTags;
use common\models\statistics\StatisticsSearch;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var Model|null $searchModel */

$searchModel = $searchModel ?? null;
$route = '/' . Yii::$app->controller->route;
$fields = [];

if ($searchModel instanceof ClanSearch) {
    $fields = [
        'id' => ['type' => 'number'],
        'name' => [],
        'tag' => [],
        'server_id' => [
            'type' => 'select',
            'items' => ArrayHelper::map(
                Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(),
                'id',
                'name'
            ),
            'prompt' => Yii::t('common', 'Все серверы'),
        ],
        'leader_username' => [],
        'privacy' => [
            'type' => 'select',
            'items' => [
                Clan::PRIVACY_OPEN => Yii::t('common', 'Открытый'),
                Clan::PRIVACY_CLOSED => Yii::t('common', 'Закрытый'),
                Clan::PRIVACY_INVITE_ONLY => Yii::t('common', 'По приглашению'),
            ],
            'prompt' => Yii::t('common', 'Любая приватность'),
        ],
    ];
} elseif ($searchModel instanceof SelectSearch) {
    $fields = [
        'id' => ['type' => 'number'],
        'name' => [],
        'status' => ['type' => 'select', 'items' => Select::getStatusList(), 'prompt' => Yii::t('common', 'Любой статус')],
        'created_at' => [],
    ];
} elseif ($searchModel instanceof StatisticsSearch) {
    $fields = [
        'id' => ['type' => 'number'],
        'steam_id' => [],
        'key' => [],
        'value' => ['type' => 'number'],
        'server_tag' => [],
        'wipe' => [],
    ];
} elseif ($searchModel instanceof ServersTagsSearch) {
    $fields = [
        'id' => ['type' => 'number'],
        'name' => [],
        'title' => [],
        'link_name' => [],
        'status' => ['type' => 'select', 'items' => ServersTags::getStatusList(), 'prompt' => Yii::t('common', 'Любой статус')],
        'color' => [],
        'sort' => ['type' => 'number'],
    ];
} elseif ($searchModel instanceof Model) {
    foreach (array_slice($searchModel->activeAttributes(), 0, 8) as $attribute) {
        $fields[$attribute] = [];
    }
}
?>

<div class="admin-filters-content generic-filters-panel">
    <div class="user-filters-mobile-header">
        <h2 class="generic-filters-panel__title"><?= Yii::t('common', 'Фильтры') ?></h2>
        <button type="button" class="filters-drawer-close ds-btn ds-btn--icon ds-btn--ghost" aria-label="Закрыть фильтры">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <?php if ($searchModel instanceof Model && $fields !== []): ?>
        <?php $form = ActiveForm::begin([
            'action' => [$route],
            'method' => 'get',
            'options' => ['class' => 'generic-filters-panel__form'],
            'fieldConfig' => [
                'options' => ['class' => 'generic-filter-field'],
                'labelOptions' => ['class' => 'generic-filter-field__label'],
                'errorOptions' => ['class' => 'generic-filter-field__error'],
            ],
        ]); ?>

        <h2 class="generic-filters-panel__title generic-filters-panel__title--desktop"><?= Yii::t('common', 'Фильтры') ?></h2>

        <?php foreach ($fields as $attribute => $config): ?>
            <?php
            $inputId = 'sidebar-filter-' . Html::getInputId($searchModel, $attribute);
            $field = $form->field($searchModel, $attribute, [
                'labelOptions' => [
                    'class' => 'generic-filter-field__label',
                    'for' => $inputId,
                ],
            ]);
            if (($config['type'] ?? 'text') === 'select') {
                echo $field->dropDownList($config['items'] ?? [], [
                    'id' => $inputId,
                    'class' => 'ds-select',
                    'prompt' => $config['prompt'] ?? null,
                ]);
            } else {
                echo $field->textInput([
                    'id' => $inputId,
                    'class' => 'ds-input',
                    'type' => ($config['type'] ?? null) === 'number' ? 'number' : 'text',
                    'autocomplete' => 'off',
                ]);
            }
            ?>
        <?php endforeach; ?>

        <div class="generic-filters-panel__actions">
            <?= Html::submitButton('<i class="fas fa-filter" aria-hidden="true"></i> ' . Yii::t('common', 'Применить'), [
                'class' => 'ds-btn ds-btn--primary',
            ]) ?>
            <?= Html::a('<i class="fas fa-rotate-left" aria-hidden="true"></i> ' . Yii::t('common', 'Сбросить'), [$route], [
                'class' => 'ds-btn ds-btn--secondary',
            ]) ?>
        </div>

        <?php ActiveForm::end(); ?>
    <?php else: ?>
        <p class="generic-filters-panel__empty"><?= Yii::t('common', 'Для этой страницы дополнительные фильтры не предусмотрены.') ?></p>
    <?php endif; ?>
</div>
