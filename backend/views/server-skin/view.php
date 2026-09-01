<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use backend\components\AccessibleKartikGridView as GridView;
use common\models\serverskin\ServerSkin;

/** @var yii\web\View $this */
/** @var common\models\serverskin\ServerSkin $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Скины сервера', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="building-view">
    <p>
        <?php if ($model->status !== ServerSkin::STATUS_ACTIVE): ?>
            <?= Html::a('Принять', ['success', 'id' => $model->id, 'returnUrl' => Yii::$app->request->url], ['class' => 'ds-btn ds-btn--success', 'data' => ['method' => 'post']]) ?>
        <?php endif; ?>
        <?php if ($model->status !== ServerSkin::STATUS_REJECT): ?>
            <?= Html::a('Отклонить', ['reject', 'id' => $model->id, 'returnUrl' => Yii::$app->request->url], ['class' => 'ds-btn ds-btn--danger', 'data' => ['method' => 'post']]) ?>
        <?php endif; ?>
    </p>
    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить этот скин?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'user_id',
                'format'    => 'raw',
                'value'          => function (ServerSkin $model) {
                    return "<a href=\"/profile/{$model->user->id}\">{$model->user->username}</a>";
                },
            ],
            'name',
            [
                'attribute'       => 'status',
                'value'           => function (ServerSkin $model) {
                    $statusList = ServerSkin::getStatusList();
                    return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                },
            ],
            'created_at',
        ],
    ]) ?>

    <div class="buildings_profile_side_images" id="mpup">
        <a href="https://steamcommunity.com/sharedfiles/filedetails/?id=<?=$model->skin_id?>" title="<?=$model->name?>"><img  src="<?=$model->image?>" alt="<?=$model->name?>"></a>
    </div>
</div>
<?=\lo\widgets\magnific\MagnificPopup::widget(
    [
        'target' => '#mpup',
        'options' => [
            'delegate'=> 'a',
            'gallery' => [
                'enabled' => true
            ],
        ],
        'effect' => 'with-zoom' //for zoom effect
    ]
);?>
