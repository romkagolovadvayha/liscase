<?php

use common\models\box\Drop;
use common\models\user\UserDrop;
use kartik\grid\GridView;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\CheckboxColumn;

/** @var yii\web\View $this */
/** @var \common\models\user\UserDropSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Предметы пользователей');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

$statusList = UserDrop::getStatusList();
?>

<div class="user-drop-index-page w-full p-4 md:p-6">
    <?= \frontend\widgets\Alert::widget() ?>

    <form id="user-drop-single-status-form" method="post" action="<?= Html::encode(Url::to(['set-status'])) ?>" class="hidden" aria-hidden="true">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <input type="hidden" name="id" id="user-drop-single-id" value="">
        <input type="hidden" name="status" id="user-drop-single-status" value="">
    </form>

    <?php $bulkForm = ActiveForm::begin([
        'action' => ['bulk-status'],
        'method' => 'post',
        'options' => ['class' => 'user-drop-bulk-form'],
    ]); ?>

    <div class="flex flex-wrap items-end gap-3 mb-4 p-3 rounded-lg bg-[hsl(0_0%_16%_/_1)] border border-[hsl(0_0%_15.3%_/_1)]">
        <div class="flex flex-col gap-1 min-w-[200px]">
            <label class="text-xs text-gray-400"><?= Yii::t('common', 'Массовая смена статуса') ?></label>
            <?= Html::dropDownList(
                'bulk_status',
                '',
                $statusList,
                [
                    'class' => 'ds-select text-sm max-w-xs',
                    'prompt' => Yii::t('common', 'Выберите статус…'),
                ]
            ) ?>
        </div>
        <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm inline-flex items-center gap-2">
            <i class="fas fa-check-double"></i>
            <?= Yii::t('common', 'Применить к выбранным') ?>
        </button>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table-auto w-full text-sm'],
        'options' => ['class' => 'admin-grid-view-dark'],
        'layout' => "{items}\n{pager}",
        'filterRowOptions' => ['style' => 'display: none;'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'columns' => [
            [
                'class' => CheckboxColumn::class,
                'checkboxOptions' => static function (UserDrop $model) {
                    return ['value' => (string) $model->id, 'class' => 'user-drop-row-check'];
                },
                'headerOptions' => ['class' => $headerCellClass, 'style' => 'width:44px'],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'label' => '',
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass, 'style' => 'width:64px'],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static function (UserDrop $model) {
                    $drop = $model->dropOne;
                    $url = $drop ? $drop->image() : null;
                    if (!$url) {
                        return '<span class="text-gray-500">—</span>';
                    }
                    return Html::tag('div', Html::img($url, [
                        'width' => 48,
                        'height' => 48,
                        'loading' => 'lazy',
                        'alt' => '',
                        'class' => 'rounded object-cover bg-[hsl(0_0%_18%_/_1)]',
                    ]), ['class' => 'flex items-center']);
                },
            ],
            [
                'label' => Yii::t('common', 'Предмет'),
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static function (UserDrop $model) {
                    $drop = $model->dropOne;
                    if (!$drop) {
                        return '<span class="text-gray-500">—</span>';
                    }
                    return Html::encode(Yii::t('database', $drop->name));
                },
            ],
            [
                'label' => Yii::t('common', 'Пользователь'),
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static function (UserDrop $model) {
                    $u = $model->user;
                    if (!$u) {
                        return '<span class="text-gray-500">—</span>';
                    }
                    $profileUrl = Url::to(['/user/profile', 'userId' => $u->id]);
                    return Html::a(Html::encode($u->username), $profileUrl, [
                        'class' => 'text-blue-400 hover:underline',
                    ]);
                },
            ],
            [
                'label' => Yii::t('common', 'Steam ID'),
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' font-mono text-xs'],
                'value' => static function (UserDrop $model) {
                    $sid = $model->user ? (string) $model->user->steam_id : '';
                    return $sid !== '' ? Html::encode($sid) : '<span class="text-gray-500">—</span>';
                },
            ],
            [
                'attribute' => 'created_at',
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' whitespace-nowrap'],
                'value' => static function (UserDrop $model) {
                    return Html::encode(Yii::$app->formatter->asDatetime($model->created_at, 'php:d.m.Y H:i'));
                },
            ],
            [
                'attribute' => 'sended_at',
                'label' => Yii::t('common', 'Дата вывода'),
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' whitespace-nowrap'],
                'value' => static function (UserDrop $model) {
                    $raw = $model->sended_at;
                    if ($raw === null || $raw === '' || $raw === '0000-00-00 00:00:00') {
                        return '<span class="text-gray-500">—</span>';
                    }
                    $ts = strtotime((string) $raw);
                    if ($ts === false || $ts <= 0) {
                        return '<span class="text-gray-500">—</span>';
                    }

                    return Html::encode(Yii::$app->formatter->asDatetime($raw, 'php:d.m.Y H:i'));
                },
            ],
            [
                'label' => Yii::t('common', 'В магазине (предмет)'),
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static function (UserDrop $model) {
                    $drop = $model->dropOne;
                    if (!$drop) {
                        return '<span class="text-gray-500">—</span>';
                    }
                    $marketOk = (int) $drop->market_status === Drop::MARKET_STATUS_ACTIVE;
                    $catalogOk = (int) $drop->status === Drop::STATUS_ACTIVE;
                    $marketLabel = Drop::getMarketStatusList()[$drop->market_status] ?? (string) $drop->market_status;
                    $catalogLabel = Drop::getStatusList()[$drop->status] ?? (string) $drop->status;
                    $badgeClass = ($marketOk && $catalogOk)
                        ? 'bg-green-600/90 text-white'
                        : 'bg-[hsl(0_0%_30%_/_1)] text-gray-200';
                    $short = ($marketOk && $catalogOk)
                        ? Yii::t('common', 'Продаётся')
                        : Yii::t('common', 'Не продаётся');
                    $detail = Html::encode($marketLabel) . ' · ' . Html::encode($catalogLabel);
                    return '<span class="inline-flex flex-col gap-0.5">'
                        . '<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ' . $badgeClass . '">' . Html::encode($short) . '</span>'
                        . '<span class="text-xs text-gray-400">' . $detail . '</span>'
                        . '</span>';
                },
            ],
            [
                'label' => Yii::t('common', 'Статус (инвентарь)'),
                'format' => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static function (UserDrop $model) use ($statusList) {
                    $opts = [];
                    foreach ($statusList as $val => $label) {
                        $opts[$val] = Html::encode($label);
                    }
                    return Html::dropDownList(
                        'ud_status_' . $model->id,
                        (string) $model->status,
                        $opts,
                        [
                            'class' => 'ds-select text-sm min-w-[160px]',
                            'data-id' => (string) $model->id,
                            'onchange' => 'var f=document.getElementById("user-drop-single-status-form");document.getElementById("user-drop-single-id").value='
                                . (int) $model->id . ';document.getElementById("user-drop-single-status").value=this.value;f.submit();',
                        ]
                    );
                },
            ],
        ],
    ]); ?>

    <?php ActiveForm::end(); ?>
</div>
