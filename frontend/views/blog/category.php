<?php

use yii\web\View;
use frontend\widgets\Alert;
use common\models\blog\BlogCategory;
use yii\widgets\ListView;

/** @var View $this */
/** @var BlogCategory $blogCategory */
/** @var \yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('database', $blogCategory->name);
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог"), 'url' => ["/posts"]];
if (!empty($blogCategory->parentCategory)) {
    $this->params['breadcrumbs'][] = ['label' => Yii::t('database', $blogCategory->parentCategory->name), 'url' => [$blogCategory->parentCategory->getUrl()]];
}
$this->params['h1'] = Yii::t('database', $blogCategory->name);
$this->params['page'] = 'blog';
$this->params['_blog_category'] = $blogCategory;
$this->params['_blog_category_block'] = true;
$this->params['_blog_comments_block'] = true;
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_keywords'] = Yii::t('database', $blogCategory->keywords);
$this->params['meta_description'] = Yii::t('database', $blogCategory->description);
?>

<?= Alert::widget() ?>
<?=$this->render('_header', [
    'dataProvider' => $dataProvider,
    'categoryId' => $blogCategory->id,
])?>
<?= ListView::widget([
                         'id'           => 'blog-list-view',
                         'dataProvider' => $dataProvider,
                         'layout'       => "{items}{pager}",
                         'itemView'     => '../blog/_item',
                         'itemOptions' => [
                             'tag' => false,
                         ],
                     ]) ?>
<?=\lo\widgets\magnific\MagnificPopup::widget(
    [
        'target' => '.blog_item_body_text_images_item_preview_wrap',
        'options' => [
            'delegate'=> 'a',
            'gallery' => [
                'enabled' => true
            ],
        ],
        'effect' => 'with-zoom' //for zoom effect
    ]
);?>
