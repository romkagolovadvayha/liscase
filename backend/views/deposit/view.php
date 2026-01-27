<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\helpers\ArrayHelper;
use common\models\invoice\Deposit;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */

$this->title = 'Депозит #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Депозиты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="deposit-view-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ds-flex ds-flex--gap-md">
                <?= Html::a('<i class="fas fa-edit"></i> Редактировать', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
                <?php if ($model->status != Deposit::STATUS_SUCCESS): ?>
                    <?= Html::a('<i class="fas fa-check"></i> Принять', ['accept', 'id' => $model->id], [
                        'class' => 'ds-btn ds-btn--success',
                        'data' => [
                            'confirm' => 'Вы уверены, что хотите принять этот депозит?',
                            'method' => 'post',
                        ],
                    ]) ?>
                <?php endif; ?>
                <?= Html::a('<i class="fas fa-trash"></i> Удалить', ['delete', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--danger',
                    'data' => [
                        'confirm' => 'Вы уверены, что хотите удалить этот депозит?',
                        'method' => 'post',
                    ],
                ]) ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    [
                        'attribute' => 'user_id',
                        'format' => 'raw',
                        'value' => function ($model) {
                            return Html::a(
                                Html::encode($model->user->username ?? 'N/A'),
                                ['/user/profile', 'userId' => $model->user_id],
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'attribute' => 'payment_type',
                        'value' => function ($model) {
                            return ArrayHelper::getValue(Deposit::getTypeList(), $model->payment_type);
                        },
                    ],
                    'amount',
                    'payment_id:ntext',
                    [
                        'attribute' => 'status',
                        'format' => 'raw',
                        'value' => function ($model) {
                            $status = ArrayHelper::getValue(Deposit::getStatusList(), $model->status);
                            $badgeClass = $model->status == Deposit::STATUS_SUCCESS 
                                ? 'ds-badge--success' 
                                : ($model->status == Deposit::STATUS_WAIT_CONFIRM 
                                    ? 'ds-badge--warning' 
                                    : 'ds-badge--danger');
                            return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    'created_at:datetime',
                ],
            ]) ?>
        </div>
    </div>
</div>
