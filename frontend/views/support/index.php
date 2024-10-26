<?php

use common\models\support\Support;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;

/** @var yii\web\View $this */
/** @var frontend\models\support\SupportSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Поддержка');
?>
<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?php echo $this->render('@frontend/views/widgets/_alert'); ?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child support">
                <div class="support_buttons">
                    <div
                            data-href="/support/create"
                            class="btn btn-success show-modal-link"
                            data-size="modal-lg"
                            data-toggl="modal"
                            data-target="modal-dialog"
                            data-title="<?=Yii::t('common', 'Новая жалоба')?>">
                        <?=Yii::t('common', 'Создать тикет')?>
                    </div>
                </div>
                <?= GridView::widget([
                                         'dataProvider' => $dataProvider,
                                         'filterModel' => $searchModel,
                                         'columns' => [
                                             [
                                                 'attribute' => 'id',
                                                 'options'   => ['width' => '120'],
                                                 'format'    => 'raw',
                                                 'value'          => function (Support $model) {
                                                     return 'ID' . $model->getNumber();
                                                 },
                                             ],
                                             [
                                                 'attribute'       => 'status',
                                                 'options'   => ['width' => '140'],
                                                 'filterType'  => GridView::FILTER_SELECT2,
                                                 'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], Support::getStatusList()),
                                                 'value'           => function (Support $model) {
                                                     $statusList = Support::getStatusList();
                                                     return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                                                 },
                                             ],
                                             [
                                                 'attribute'       => 'server_tag',
                                                 'filterType'  => GridView::FILTER_SELECT2,
                                                 'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], \common\models\servers\Servers::getServers()),
                                                 'value'           => function (Support $model) {
                                                     $statusList = \common\models\servers\Servers::getServers();
                                                     return \yii\helpers\ArrayHelper::getValue($statusList, $model->server_tag);
                                                 },
                                             ],
                                             [
                                                 'attribute' => 'created_at',
                                                 'options'   => ['width' => '200'],
                                                 'class' => \common\components\grid\DateColumn::class,
                                             ],
                                             [
                                                 'attribute' => 'updated_at',
                                                 'options'   => ['width' => '200'],
                                                 'class' => \common\components\grid\DateColumn::class,
                                             ],
                                             [
                                                 'attribute' => '',
                                                 'format'    => 'raw',
                                                 'options'   => ['width' => '120'],
                                                 'value'           => function (Support $model) {

                                                     return "<a href=\"/support/ticket?id={$model->getNumber()}\" class=\"btn btn-primary\">Перейти</a>";
                                                 },
                                             ],
                                         ],
                                     ]); ?>
            </div>
        </main>
    </div>
</div>