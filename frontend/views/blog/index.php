<?php

use yii\widgets\ListView;
use frontend\widgets\Alert;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Баги и новости Rust');

$this->params['h1'] = Yii::t('common', 'Блог');
$this->params['page'] = 'blog';
$this->params['_blog_comments_block'] = true;
$this->params['_blog_category_block'] = true;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог")];
?>

<?= Alert::widget() ?>
<?=$this->render('_header', [
    'dataProvider' => $dataProvider,
])?>
<?= ListView::widget([
                         'id'           => 'blog-list-view',
                         'dataProvider' => $dataProvider,
                         'layout'       => "{items}{pager}",
                         'itemView'     => '../blog/_item',
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