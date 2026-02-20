<?php

use yii\helpers\Html;
use yii\helpers\Json;
use common\models\bonus\AudienceBonus;

/** @var yii\web\View $this */
/** @var common\models\bonus\AudienceBonus $model */

$this->title = 'Начисление бонуса #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Бонусы аудитории', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$rowClass = 'flex flex-wrap gap-2 py-3 border-b border-[hsl(0_0%_15.3%_/_1)] last:border-b-0';
$labelClass = 'text-xs text-gray-400 uppercase tracking-wide w-full md:w-32 flex-shrink-0';
$valueClass = 'text-white flex-1 min-w-0';
?>
<div class="audience-bonus-view-page w-full p-4 lg:p-6">
    <div class="max-w-4xl">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h1 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Html::encode($this->title) ?></h1>
            </div>
            <div class="p-4">
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">ID</div>
                    <div class="<?= $valueClass ?>"><?= (int)$model->id ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">Тип аудитории</div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->getAudienceTypeName()) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">Параметры</div>
                    <div class="<?= $valueClass ?>">
                        <?php $params = $model->getParameters(); ?>
                        <?php if (empty($params)): ?>
                            <span class="text-gray-500">—</span>
                        <?php else: ?>
                            <pre class="bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded px-3 py-2 text-sm text-gray-300 overflow-x-auto"><?= Html::encode(Json::encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('message_template') ?></div>
                    <div class="<?= $valueClass ?> whitespace-pre-wrap"><?= nl2br(Html::encode($model->message_template)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">Тестовые пользователи</div>
                    <div class="<?= $valueClass ?>">
                        <?php $testUserIds = $model->getTestUserIds(); ?>
                        <?php if (empty($testUserIds)): ?>
                            <span class="text-gray-500">—</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-600/80 text-white">Да (<?= count($testUserIds) ?> пользователей)</span>
                            <span class="block text-gray-400 text-xs mt-1"><?= Html::encode(implode(', ', $testUserIds)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">Количество пользователей</div>
                    <div class="<?= $valueClass ?>"><?= (int)$model->total_users ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">Общая сумма</div>
                    <div class="<?= $valueClass ?>"><?= number_format($model->total_amount, 2, '.', ' ') ?> РУБ</div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('created_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">Создал</div>
                    <div class="<?= $valueClass ?>"><?= $model->createdBy ? Html::encode($model->createdBy->username) : '<span class="text-gray-500">—</span>' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
