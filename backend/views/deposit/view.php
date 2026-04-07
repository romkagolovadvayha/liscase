<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use common\models\invoice\Deposit;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */

$this->title = 'Депозит #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Депозиты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$rowClass = 'flex flex-wrap gap-2 py-3 border-b border-[hsl(0_0%_15.3%_/_1)] last:border-b-0';
$labelClass = 'text-xs text-gray-400 uppercase tracking-wide w-full md:w-32 flex-shrink-0';
$valueClass = 'text-white flex-1 min-w-0';
?>
<div class="deposit-view-page w-full p-4 lg:p-6">
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
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('user_id') ?></div>
                    <div class="<?= $valueClass ?>">
                        <?= Html::a(
                            Html::encode($model->user->username ?? 'N/A'),
                            '/profile/' . $model->user_id,
                            ['class' => 'text-blue-400 hover:underline']
                        ) ?>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('payment_type') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode(ArrayHelper::getValue(Deposit::getTypeList(), $model->payment_type)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('amount') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->amount) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('payment_id') ?></div>
                    <div class="<?= $valueClass ?>"><?= nl2br(Html::encode($model->payment_id)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('status') ?></div>
                    <div class="<?= $valueClass ?>">
                        <?php
                        $status = ArrayHelper::getValue(Deposit::getStatusList(), $model->status);
                        $badgeClass = $model->status == Deposit::STATUS_SUCCESS
                            ? 'bg-green-600/80 text-white'
                            : ($model->status == Deposit::STATUS_WAIT_CONFIRM ? 'bg-amber-600/80 text-white' : 'bg-red-600/80 text-white');
                        ?>
                        <span class="px-2 py-0.5 rounded text-xs font-medium <?= $badgeClass ?>"><?= Html::encode($status) ?></span>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('created_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
