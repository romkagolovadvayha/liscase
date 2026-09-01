<?php

use yii\helpers\ArrayHelper;
use backend\models\TelegramConstructor;
use backend\components\AccessibleKartikGridView as GridView;
use yii\bootstrap5\Html;
use common\models\vk\VkUser;

/** @var $audienceId */
/** @var $audienceCount int */
/** @var $vkUsers VkUser[] */

$this->title = 'Аудитория ВКонтакте: ' . ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $audienceId);
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor/index']];
$this->params['breadcrumbs'][] = $this->title;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="tc-audience-vk-page w-full">
    <div class="p-4 lg:p-6 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-semibold text-white"><?= Yii::$app->formatter->asInteger($audienceCount) ?></span>
            <span class="text-sm text-gray-400">Всего получателей</span>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => new \yii\data\ArrayDataProvider([
            'allModels' => $vkUsers,
            'pagination' => ['pageSize' => 50],
        ]),
        'tableOptions' => ['class' => 'table-auto w-full text-sm'],
        'options'      => ['class' => 'admin-grid-view-dark tc-audience-grid'],
        'layout'       => "{items}\n{pager}",
        'filterRowOptions' => ['style' => 'display: none;'],
        'bordered'     => false,
        'striped'      => false,
        'hover'        => true,
        'columns'      => [
            [
                'attribute' => 'vk_user_id',
                'label'     => 'VK ID',
                'options'   => ['width' => '120'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'first_name',
                'label'     => 'Имя',
                'format'    => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (VkUser $model) {
                    return Html::encode($model->first_name . ' ' . $model->last_name);
                },
            ],
            [
                'attribute' => 'screen_name',
                'label'     => 'Screen Name',
                'format'    => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (VkUser $model) {
                    if ($model->screen_name) {
                        return Html::a(Html::encode($model->screen_name), 'https://vk.com/' . $model->screen_name, [
                            'target' => '_blank',
                            'class' => 'text-white hover:underline',
                            'style' => 'text-decoration: none;',
                        ]);
                    }
                    return '<span class="text-gray-500">—</span>';
                },
            ],
            [
                'attribute' => 'can_send_message',
                'label'     => 'Можно отправлять',
                'format'    => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (VkUser $model) {
                    return $model->can_send_message
                        ? '<span class="ds-badge ds-badge--success">Да</span>'
                        : '<span class="ds-badge ds-badge--danger">Нет</span>';
                },
            ],
            [
                'attribute' => 'updated_at',
                'label'     => 'Обновлено',
                'format'    => 'datetime',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
        ],
    ]); ?>
</div>

<style>
.tc-audience-grid { background: hsl(0 0% 10% / 1) !important; }
.tc-audience-grid .table, .tc-audience-grid table, .tc-audience-grid .kv-grid-table { background: hsl(0 0% 10% / 1) !important; border-collapse: collapse; color: white !important; border: none !important; }
.tc-audience-grid .table thead th, .tc-audience-grid table thead th { background: hsl(0 0% 20.4% / 1) !important; color: hsl(0 0% 70% / 1) !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tc-audience-grid .table tbody td, .tc-audience-grid table tbody td { background: transparent !important; color: white !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tc-audience-grid .table tbody tr:hover { background: hsl(0 0% 15% / 1) !important; }
.tc-audience-grid .pagination, .tc-audience-grid .kv-panel-pager { background: hsl(0 0% 10% / 1) !important; color: white !important; }
.tc-audience-grid .pagination .page-link { background: hsl(0 0% 20.4% / 1) !important; color: white !important; border-color: hsl(0 0% 15.3% / 1) !important; }
</style>
