<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;
use common\models\blog\BlogCategory;

/** @var common\models\blog\BlogCategory $category */

$blockName = Yii::t('common', 'Категории');
if (!empty($category)) {
    $categoryId = $category->id;
    $categoryName = $category->name;
    if (!empty($category->parentCategory)) {
        $categoryId = $category->parentCategory->id;
        $categoryName = $category->parentCategory->name;
    }
    $blockName = Yii::t('database', $categoryName);
}
$categories = BlogCategory::find()
    ->cache(60)
    ->andWhere(['status' => BlogCategory::STATUS_ACTIVE]);
if (!empty($category)) {
    $categories->andWhere(['blog_category_id' => $categoryId]);
} else {
    $categories->andWhere('blog_category_id is NULL');
}

$dataProvider = new ActiveDataProvider([
    'query' => $categories,
    'pagination' => false,
    'sort'  => [
        'defaultOrder' => ['created_at' => SORT_DESC],
    ],
]);
?>

<section class="block">
    <header class="block_header">
        <div class="block_header_container">
            <h2 class="block_header_container_title"><?=$blockName?></h2>
        </div>
    </header>
    <div class="block_body">
        <ul class="block_body_category_list">
            <?= ListView::widget([
                'id'           => 'blog-category-list-view',
                'dataProvider' => $dataProvider,
                'layout'       => "{items}",
                'itemView'     => '_side_category_list_item',
                'itemOptions' => [
                    'tag' => false,
                ],
            ]) ?>
        </ul>
    </div>
</section>