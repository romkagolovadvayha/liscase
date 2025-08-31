<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;
use common\models\comment\Comment;

$dataProvider = new ActiveDataProvider([
                                           'query' => Comment::find()->alias('c')->cache(30)->limit(5)->joinWith('blog')->andWhere(['c.status' => Blog::STATUS_ACTIVE])->orderBy(['c.createdAt' => SORT_DESC]),
                                           'pagination' => false,
                                           'sort'  => [
                                               'defaultOrder' => ['createdAt' => SORT_DESC],
                                           ],
                                       ]);
?>

<section class="stats-aside__stat-block stat-block">
    <h4 class="stat-block__title"><?=Yii::t('common', 'Последние комментарии')?></h4>
    <div class="tab-content">
        <?= ListView::widget([
                                 'id'           => 'blog-last-comments-list-view',
                                 'dataProvider' => $dataProvider,
                                 'options' => [
                                     'class'           => 'stat-block__list',
                                 ],
                                 'layout'       => "{items}",
                                 'itemView'     => '_side_comments_list_item',
                                 'itemOptions' => [
                                     'tag' => false,
                                 ],
                             ]) ?>
    </div>
</section>