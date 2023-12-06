<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;

/** @var \common\models\user\User $user */

$models = Blog::find()->andWhere(['user_id' => $user->id])->andWhere(['status' => Blog::STATUS_ACTIVE])->all();

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
            <h2 class="block_header_container_title"><?=Yii::t('common', 'Записи пользователя')?></h2>
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
                <p><?=Yii::t('common', 'Пользователь еще не сделал ни одной записи в блог.')?></p>
            <?php endif;?>
        </ul>
    </div>
</section>