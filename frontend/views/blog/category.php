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
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_keywords'] = Yii::t('database', $blogCategory->keywords);
$this->params['meta_description'] = Yii::t('database', $blogCategory->description);
?>

<main id="main" role="main">
    <div class="content_wrapper container">
        <div class="content">
            <?= Alert::widget() ?>
            <?=$this->render('_header', [
                'dataProvider' => $dataProvider,
                'title' => $blogCategory->name,
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
        </div>
        <div class="side">
            <div class="side_container">
                <?=$this->render('../layouts/_side_category_list', [
                        'category' => $blogCategory
                ])?>
                <?=$this->render('../layouts/_side_popular_posts')?>
                <?=$this->render('../layouts/_side_comments_list')?>
            </div>
        </div>
    </div>
</main>

