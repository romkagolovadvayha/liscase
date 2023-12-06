<?php

use common\models\blog\BlogCategory;
use yii\widgets\ActiveForm;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */


$items = [];
/** @var BlogCategory[] $categories */
$categories = BlogCategory::find()->andWhere([
    'blog_category_id' => null,
])->all();
foreach ($categories as $category) {
    $item = [
        'label'   => Yii::t('database', $category->name),
        'url'     => $category->getUrl(),
        'items'     => [],
    ];
    $subCategories = BlogCategory::find()->andWhere([
        'blog_category_id' => $category->id,
    ])->all();
    foreach ($subCategories as $subCategory) {
        $subItem = [
            'label'   => Yii::t('database', $subCategory->name),
            'url'     => $subCategory->getUrl()
        ];
        $item['items'][] = $subItem;
    }
    $items[] = $item;
}

?>
<?=Nav::widget([
    'items' => $items,
    'options' => ['class' =>'header_nav_left_menu'],
]);
?>