<?php

use yii\helpers\ArrayHelper;
use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use backend\models\AudienceSearch;
use kartik\grid\GridView;
use yii\bootstrap5\Html;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $audienceId */
/** @var $audienceCount int */
/** @var $audience array */

$this->title = "Аудитория: " . ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $audienceId);

?>

<div class="row row-border counters-list">
    <div class="col-md-2 col-sm-6 col-xs-12">
        <h3><?= Yii::$app->formatter->asInteger($audienceCount); ?></h3>
        <h5>Всего получателей</h5>
    </div>
</div>


<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'attribute' => 'id',
            'options'   => ['width' => '100'],
        ],
        [
            'attribute' => 'username',
            'format'    => 'raw',
            'value'          => function (AudienceSearch $model) {
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                return Html::a($model->username, $url);
            },
        ],
        [
            'attribute' => 'steam_id',
            'format'    => 'raw',
            'value'          => function (AudienceSearch $model) {
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                return Html::a($model->steam_id, $url);
            },
        ],
        [
            'attribute' => 'ref_code',
            'label'     => 'Реф.код',
            'format'    => 'raw',
            'value'          => function (AudienceSearch $model) {
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                return Html::a($model->ref_code, $url);
            },
        ],
        [
            'attribute'       => 'status',
            'filter'          => \common\models\user\User::getStatusList(),
            'value'           => function (AudienceSearch $model) {
                return \yii\helpers\ArrayHelper::getValue(\common\models\user\User::getStatusList(), $model->status);
            },
        ],
        [
            'class' => \common\components\grid\DateColumn::class,
        ],
    ],
]);
?>
