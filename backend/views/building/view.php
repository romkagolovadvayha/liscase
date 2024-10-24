<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use kartik\grid\GridView;

/** @var yii\web\View $this */
/** @var common\models\building\Building $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Buildings', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="building-view">
    <p>
        <?php if ($model->status !== \common\models\building\Building::STATUS_ACTIVE): ?>
            <?= Html::a('Принять', ['success', 'id' => $model->id], ['class' => 'btn btn-success']) ?>
        <?php endif; ?>
        <?php if ($model->status !== \common\models\building\Building::STATUS_REJECT): ?>
            <?= Html::a('Отклонить', ['reject', 'id' => $model->id], ['class' => 'btn btn-danger']) ?>
        <?php endif; ?>
    </p>
    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
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
                'value'          => function (\common\models\building\Building $model) {
                    return "<a href=\"/user/profile?userId={$model->user->id}\">{$model->user->username}</a>";
                },
            ],
            'name',
            'description',
            [
                'label'       => 'Квадрат',
                'attribute'       => 'location',
                'value'           => function (\common\models\building\Building $model) {
                    return $model->location;
                },
            ],
            [
                'attribute'       => 'status',
                'value'           => function (\common\models\building\Building $model) {
                    $statusList = \common\models\building\Building::getStatusList();
                    return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                },
            ],
            [
                'attribute'       => 'server_tag',
                'value'           => function (\common\models\building\Building $model) {
                    $statusList = \common\models\servers\Servers::getServers();
                    return \yii\helpers\ArrayHelper::getValue($statusList, $model->server_tag);
                },
            ],
            [
                'label'       => 'Вайп',
                'attribute'       => 'wipe',
                'value'           => function (\common\models\building\Building $model) {
                    return $model->wipe;
                },
            ],
            'created_at',
        ],
    ]) ?>

    <h2>Жильцы (<?=count($model->buildingResident)?>)</h2>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <?php foreach ($model->buildingResident as $resident): ?>
            <a  title="<?=Yii::t('common', 'Открыть профиль игрока')?> <?=$resident->user->username?>"
                target="_blank"
                href="/stats/player?steamId=<?=$resident->user->steam_id?>&server=<?=$model->server_tag?>"
                class="buildings_profile_users_item">
                <span class="buildings_profile_users_item_name"><?=$resident->user->username?></span>
            </a>
    <?php endforeach; ?>
    </div>

    <h2>Фотографии (<?=count($model->buildingImage)?>)</h2>
    <div class="buildings_profile_side_images" id="mpup">
        <?php foreach ($model->buildingImage as $i => $image): ?>
            <a href="<?=$image->getPublicUrl()?>" title="<?=$model->name?>"><img  src="<?=$image->getPublicUrlPreview()?>" alt="<?=$model->name?>"></a>
        <?php endforeach; ?>
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