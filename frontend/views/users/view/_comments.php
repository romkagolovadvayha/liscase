<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;
use common\models\comment\Comment;

/** @var \common\models\user\User $user */

$dataProvider = new ActiveDataProvider([
                                           'query' => Comment::find()->cache(360)->andWhere(['createdBy' => $user->id])->limit(10)->andWhere(['status' => Blog::STATUS_ACTIVE])->orderBy(['createdAt' => SORT_DESC]),
                                           'pagination' => false,
                                           'sort'  => [
                                               'defaultOrder' => ['createdAt' => SORT_DESC],
                                           ],
                                       ]);
?>

<section class="block">
    <header class="block_header">
        <div class="block_header_container">
            <h2 class="block_header_container_title"><?=Yii::t('common', 'Последние комментарии')?></h2>
        </div>
    </header>
    <div class="block_body">
        <ul class="block_body_article_list">
            <?= ListView::widget([
                                     'id'           => 'blog-last-comments-list-view',
                                     'dataProvider' => $dataProvider,
                                     'layout'       => "{items}",
                                     'itemView'     => '../../layouts/_side_comments_list_item',
                                     'itemOptions' => [
                                         'tag' => false,
                                     ],
                                 ]) ?>
        </ul>
    </div>
</section>