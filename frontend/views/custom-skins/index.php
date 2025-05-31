<?php

use common\models\building\Building;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\widgets\ListView;
use frontend\assets\BuildingsAsset;
use frontend\widgets\Alert;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$userBuildingsWait = \common\models\serverskin\ServerSkin::find()
                             ->andWhere(['user_id' => Yii::$app->user->id])
                             ->andWhere(['status' => \common\models\serverskin\ServerSkin::STATUS_WAIT])
                             ->exists();

$userLikes = \common\models\serverskin\ServerSkinLike::find()
    ->select('DISTINCT(server_skin_id)')
    ->andWhere(['user_id' => Yii::$app->user->id])
    ->createCommand()
    ->queryColumn();

\frontend\assets\CustomSkinAsset::register($this);

$this->title = Yii::t('common', 'Скины из мастерской');
?>
<?= Alert::widget() ?>
<div class="server_info_page">
    <div class="custom-skins">
            <div class="custom-skins_buttons">
                <?= Html::a(Yii::t('common', 'Добавить скин'), ['create'], [
                        'class' => 'button button-secondary show-modal-link',
                        'data-title' => Yii::t('common', 'Добавить скин'),
                        'data-size' => 'modal-lg',
                        'data-toggl' => 'modal',
                        'data-target' => 'modal-dialog'
                    ]) ?>
            </div>
        <div class="custom-skins_content">
            <?php if ($userBuildingsWait): ?>
                <div class="custom-skins_content_moderation">
                    <?=Yii::t('common', 'Ваш скин проверки, как только его проверят он появится в списке ниже и на серверах.')?>
                </div>
            <?php endif; ?>
            <div class="custom-skins_content_list">
                <?= ListView::widget([
                                         'dataProvider' => $dataProvider,
                                         'itemView' => '_item',
                                         'viewParams' => [
                                             'userLikes' => $userLikes
                                         ],
                                         'layout' => '<div class="custom-skins_content_list_items">{items}</div>{pager}',
                                     ]); ?>
            </div>
        </div>
    </div>
</div>