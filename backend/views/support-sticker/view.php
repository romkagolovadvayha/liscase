<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\support\SupportSticker;

/** @var yii\web\View $this */
/** @var common\models\support\SupportSticker $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Стикеры поддержки'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="support-sticker-view">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <p>
            <?= Html::a(Yii::t('common', 'Изменить'), ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a(Yii::t('common', 'Удалить'), ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => Yii::t('common', 'Вы уверены, что хотите удалить этот стикер?'),
                    'method' => 'post',
                ],
            ]) ?>
        </p>

        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                'id',
                'code',
                'name',
                [
                    'attribute' => 'type',
                    'value' => function (SupportSticker $model) {
                        $typeList = SupportSticker::getTypeList();
                        return \yii\helpers\ArrayHelper::getValue($typeList, $model->type);
                    },
                ],
                [
                    'attribute' => 'file',
                    'format' => 'raw',
                    'value' => function (SupportSticker $model) {
                        if ($model->type === SupportSticker::TYPE_IMAGE) {
                            return Html::img($model->getPublicUrl(), ['style' => 'max-width: 300px; max-height: 300px;']);
                        } else {
                            return Html::tag('video', '', [
                                'src' => $model->getPublicUrl(),
                                'style' => 'max-width: 300px; max-height: 300px;',
                                'controls' => true
                            ]);
                        }
                    },
                ],
                [
                    'attribute' => 'status',
                    'value' => function (SupportSticker $model) {
                        $statusList = SupportSticker::getStatusList();
                        return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                    },
                ],
                'width',
                'height',
                'sort',
                [
                    'attribute' => 'created_at',
                    'format' => 'datetime',
                ],
                [
                    'attribute' => 'updated_at',
                    'format' => 'datetime',
                ],
            ],
        ]) ?>

        <div class="form-group" style="margin-top: 20px;">
            <label>HTML-тег для использования:</label>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;"><?= Html::encode($model->getHtmlTag()) ?></pre>
        </div>
    </div>
</div>










