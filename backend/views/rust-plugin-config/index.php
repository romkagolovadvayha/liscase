<?php

use common\models\rustplugin\RustPluginConfig;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfigSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Конфиги плагинов Rust';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rust-plugin-config-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <?= Html::a('<i class="fas fa-plus"></i> Добавить', ['create'], ['class' => 'ds-btn ds-btn--success']) ?>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    [
                        'attribute' => 'id',
                        'options'   => ['width' => '50'],
                    ],
                    'name:ntext',
                    [
                        'attribute' => 'created_at',
                        'format' => 'datetime',
                        'options'   => ['width' => '180'],
                    ],
                    [
                        'attribute' => 'updated_at',
                        'format' => 'datetime',
                        'options'   => ['width' => '180'],
                    ],
                    [
                        'class' => ActionColumn::className(),
                        'options'   => ['width' => '80'],
                        'urlCreator' => function ($action, RustPluginConfig $model, $key, $index, $column) {
                            return Url::toRoute([$action, 'id' => $model->id]);
                        }
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>

