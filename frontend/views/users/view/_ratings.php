<?php

use common\models\auth\AuthItem;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\widgets\ListView;
use common\models\blog\Blog;
use common\models\blog\BlogRating;
use yii\data\ActiveDataProvider;

/** @var \common\models\user\User $user */

$blogIds = array_keys(BlogRating::find()
    ->andWhere(['user_id' => $user->id])
    ->select('blog_id')
    ->distinct(true)
    ->indexBy('blog_id')
    ->asArray()
    ->all());

$models = Blog::find()
    ->andWhere(['IN', 'id', $blogIds])
    ->andWhere(['status' => Blog::STATUS_ACTIVE])
    ->limit(10)
    ->all();

$dataProvider = new ActiveDataProvider([
    'models' => $models,
    'pagination' => false,
    'sort'  => [
        'defaultOrder' => ['views' => SORT_DESC],
    ],
]);
?>

<section class="block">
    <header class="block_header">
        <div class="block_header_container">
            <h2 class="block_header_container_title"><?=Yii::t('common', 'Пользователю понравилось')?></h2>
        </div>
    </header>
    <div class="block_body">
        <ul class="block_body_article_list">
            <?php if (!empty($models)):?>
                <?= ListView::widget([
                    'id'           => 'blog-user-list-view',
                    'dataProvider' => $dataProvider,
                    'layout'       => "{items}",
                    'itemView'     => '../../layouts/_side_popular_posts_item',
                    'itemOptions' => [
                        'tag' => false,
                    ],
                ]) ?>
            <?php else: ?>
                <p><?=Yii::t('common', 'Пользователь еще не оценивал ни одной записи в блоге.')?></p>
            <?php endif;?>
        </ul>
    </div>
</section>