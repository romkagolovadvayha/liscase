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

<section class="stats-aside__stat-block stat-block">
    <h4 class="stat-block__title"><?=$blockName?></h4>
    <div class="tab-content">
        <?= ListView::widget([
                                 'id'           => 'blog-category-list-view',
                                 'dataProvider' => $dataProvider,
                                 'layout'       => "{items}",
                                 'itemView'     => '_side_category_list_item',
                                 'itemOptions' => [
                                     'tag' => false,
                                 ],
                             ]) ?>
    </div>
</section>