<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;

$dataProvider = new ActiveDataProvider([
    'query' => Blog::find()->cache(360)->limit(5)->andWhere(['status' => Blog::STATUS_ACTIVE])->andWhere(['>', 'views', 0])->orderBy('RAND()'),
    'pagination' => false,
    'sort'  => [
        'defaultOrder' => ['views' => SORT_DESC],
    ],
]);
?>

<section class="block">
    <header class="block_header">
        <div class="block_header_container">
            <h2 class="block_header_container_title"><?=Yii::t('common', 'Читают сейчас')?></h2>
        </div>
    </header>
    <div class="block_body">
        <ul class="block_body_article_list">
            <?= ListView::widget([
                'id'           => 'blog-popular-list-view',
                'dataProvider' => $dataProvider,
                'layout'       => "{items}",
                'itemView'     => '_side_popular_posts_item',
                'itemOptions' => [
                    'tag' => false,
                ],
            ]) ?>
        </ul>
    </div>
</section>