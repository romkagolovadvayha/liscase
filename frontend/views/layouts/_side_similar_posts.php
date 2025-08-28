<?php
use yii\widgets\ListView;
use yii\data\ArrayDataProvider;
/** @var \common\models\blog\Blog $model */

$models = \common\models\blog\Blog::getSimilarPostsFulltext($model, 5);

$dataProvider = new ArrayDataProvider([
                                          'allModels'  => $models,
                                          'pagination' => false,
                                          'sort'       => [
                                              'attributes'  => ['created_at'],
                                              'defaultOrder'=> ['created_at' => SORT_DESC],
                                          ],
                                      ]);
?>
<section class="stats-aside__stat-block stat-block">
    <h3 class="stat-block__title"><?= Yii::t('common', 'Похожие записи') ?></h3>
    <div class="tab-content">
        <?= ListView::widget([
                                 'id'           => 'blog-popular-list-view',
                                 'options'      => ['class' => 'stat-block__list'],
                                 'dataProvider' => $dataProvider,
                                 'layout'       => "{items}",
                                 'itemView'     => '_side_popular_posts_item',
                                 'itemOptions'  => ['tag' => false],
                             ]) ?>
    </div>
</section>
