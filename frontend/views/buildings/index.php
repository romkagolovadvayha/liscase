<?php

use common\models\building\Building;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\widgets\ListView;
use frontend\assets\BuildingsAsset;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$userBuildingsWait = Building::find()
                             ->andWhere(['user_id' => Yii::$app->user->id])
                             ->andWhere(['status' => Building::STATUS_WAIT])
                             ->exists();

$userLikes = \common\models\building\BuildingLike::find()
    ->select('DISTINCT(building_id)')
    ->andWhere(['user_id' => Yii::$app->user->id])
    ->createCommand()
    ->queryColumn();

BuildingsAsset::register($this);

$this->title = Yii::t('common', 'Постройки игроков');
?>

<div class="server_info_page">
    <div class="buildings">
        <?php if (!$userBuildingsWait): ?>
            <div class="buildings_buttons">
                <?= Html::a(Yii::t('common', 'Добавить свою постройку'), ['create'], ['class' => 'button button-secondary']) ?>
            </div>
        <?php endif; ?>
        <div class="buildings_content">
            <?php if ($userBuildingsWait): ?>
                <div class="buildings_content_moderation">
                    <?=Yii::t('common', 'Ваша постройка ожидает проверки, как только ее проверят она появится в списке ниже.')?>
                </div>
            <?php endif; ?>
            <div class="buildings_content_list">
                <?= ListView::widget([
                                         'dataProvider' => $dataProvider,
                                         'itemView' => '_item',
                                         'viewParams' => [
                                             'userLikes' => $userLikes
                                         ],
                                         'layout' => '<div class="buildings_content_list_items">{items}</div>{pager}',
                                     ]); ?>
            </div>
        </div>
    </div>
</div>