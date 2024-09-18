<?php

use backend\models\TelegramConstructor;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use common\components\helpers\Role;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $countTelegramUsers */
/** @var $model TelegramConstructor */

$this->title = Yii::t('common', 'Конструктор сообщений телеграм бота');
?>

<div class="row row-border counters-list">
    <div class="col-md-2 col-sm-6 col-xs-12">
        <h3><?= Yii::$app->formatter->asInteger($countTelegramUsers); ?></h3>
        <h5>Всего получателей</h5>
    </div>
</div>

<div class="m-t-20">
    <?= Html::a(Yii::t('common', 'Создать новую рассылку'),
        '/telegram-constructor/create',
        ['class' => 'btn btn-success']); ?>

    <?= Html::a(Yii::t('common', 'Сообщения для рассылок'),
        '/telegram-constructor-message',
        ['class' => 'btn btn-success']); ?>
</div>

<div class="mt-4">
    <?= GridView::widget([
                             'dataProvider' => $dataProvider,
                             'filterModel'  => $searchModel,
                             'columns'      => [
                                 [
                                     'attribute' => 'id',
                                     'options'  => [
                                         'width' => '60'
                                     ],
                                 ],
                                 [
                                     'attribute' => 'title',
                                 ],
                                 [
                                     'attribute' => 'bot_id',
                                     'filter'    => TelegramConstructor::getBotList(),
                                     'value'     => function ($model) {
                                         return ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id);
                                     },
                                 ],
                                 [
                                     'attribute' => 'audience_id',
                                     'filter'    => TelegramConstructor::getAudienceList(),
                                     'value'     => function ($model) {
                                         return ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $model->audience_id);
                                     },
                                 ],
                                 [
                                     'attribute' => 'message',
                                     'label' => 'Сообщение',
                                     'format' => 'raw',
                                     'value'     => function (\backend\models\TelegramConstructorSearch $model) {
                                         if (empty($model->telegramConstructorMessage)) {
                                             return 'Удалено';
                                         }
                                         return Html::a($model->telegramConstructorMessage->title, ['/telegram-constructor-message/update', 'id' => $model->telegramConstructorMessage->id]);
                                     },
                                 ],
                                 [
                                     'attribute'      => 'status',
                                     'filter'         => \kartik\select2\Select2::widget([
                                                                                             'model'         => $searchModel,
                                                                                             'attribute'     => 'status',
                                                                                             'data'          => ArrayHelper::merge(['all' => 'Все'], TelegramConstructor::getStatusList()),
                                                                                             'options'       => [
                                                                                                 'class'       => 'form-control',
                                                                                                 'placeholder' => '...',
                                                                                             ],
                                                                                             'pluginOptions' => [
                                                                                                 'allowClear'    => true,
                                                                                                 'selectOnClose' => true,
                                                                                             ],
                                                                                         ]),
                                     'contentOptions' => [
                                         'width' => 120,
                                     ],
                                     'value'          => function ($model) {
                                         return ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status);
                                     },
                                 ],
                                 [
                                     'class' => \common\components\grid\DateColumn::class,
                                 ],
                                 [
                                     'class'    => 'yii\grid\ActionColumn',
                                     'template' => '{play} {update} {delete}',
                                     'options'  => [
                                         'width' => '130'
                                     ],
                                     'buttons'  => [
                                         'play' => function ($url, $model) {
                                             if ($model->status !== TelegramConstructor::STATUS_NEW) {
                                                 return '';
                                             }
                                             if (!Yii::$app->user->identity->canRoles([Role::ROLE_ADMIN])) {
                                                 return Html::tag('span', Html::a(Html::icon('play'), '#', [
                                                     'class' => 'btn btn-sm btn-default disabled',
                                                 ]), [
                                                                      'title' => Yii::t('common', 'Недостаточно прав для использования')
                                                                  ]);
                                             }
                                             return \common\components\grid\ManageButton::play($url);
                                         },
                                         'update' => function ($url, $model) {
                                             if ($model->status !== TelegramConstructor::STATUS_NEW) {
                                                 return '';
                                             }
                                             return \common\components\grid\ManageButton::update($url);
                                         },
                                         'delete' => function ($url, $model) {
                                             if ($model->status !== TelegramConstructor::STATUS_NEW) {
                                                 return '';
                                             }
                                             if (!Yii::$app->user->identity->canRoles([Role::ROLE_ADMIN])) {
                                                 return Html::tag('span', Html::a(Html::icon('trash'), '#', [
                                                     'class' => 'btn btn-sm btn-default disabled',
                                                 ]), [
                                                                      'title' => Yii::t('common', 'Недостаточно прав для использования')
                                                                  ]);
                                             }
                                             return \common\components\grid\ManageButton::delete($url) ;
                                         },
                                     ],
                                 ],
                             ],
                         ]);
    ?>
</div>
