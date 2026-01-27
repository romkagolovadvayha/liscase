<?php

use yii\helpers\ArrayHelper;
use backend\models\TelegramConstructor;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\vk\VkUser;

/** @var $audienceId */
/** @var $audienceCount int */
/** @var $vkUsers VkUser[] */

$this->title = "Аудитория ВКонтакте: " . ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $audienceId);

?>

<div class="row row-border counters-list">
    <div class="col-md-2 col-sm-6 col-xs-12">
        <h3><?= Yii::$app->formatter->asInteger($audienceCount); ?></h3>
        <h5>Всего получателей</h5>
    </div>
</div>

<?= GridView::widget([
    'dataProvider' => new \yii\data\ArrayDataProvider([
        'allModels' => $vkUsers,
        'pagination' => [
            'pageSize' => 50,
        ],
    ]),
    'columns' => [
        [
            'attribute' => 'vk_user_id',
            'label' => 'VK ID',
            'options' => ['width' => '150'],
        ],
        [
            'attribute' => 'first_name',
            'label' => 'Имя',
            'format' => 'raw',
            'value' => function (VkUser $model) {
                return Html::encode($model->first_name . ' ' . $model->last_name);
            },
        ],
        [
            'attribute' => 'screen_name',
            'label' => 'Screen Name',
            'format' => 'raw',
            'value' => function (VkUser $model) {
                if ($model->screen_name) {
                    return Html::a($model->screen_name, 'https://vk.com/' . $model->screen_name, ['target' => '_blank']);
                }
                return '-';
            },
        ],
        [
            'attribute' => 'can_send_message',
            'label' => 'Можно отправлять',
            'format' => 'raw',
            'value' => function (VkUser $model) {
                return $model->can_send_message 
                    ? '<span class="badge bg-success">Да</span>' 
                    : '<span class="badge bg-danger">Нет</span>';
            },
        ],
        [
            'attribute' => 'updated_at',
            'label' => 'Обновлено',
            'format' => 'datetime',
        ],
    ],
]);
?>

