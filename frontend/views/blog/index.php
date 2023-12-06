<?php

use yii\widgets\ListView;
use frontend\widgets\Alert;
use common\models\settings\Settings;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

$this->title = Settings::getByKey('title');

?>
<main id="main" role="main">
    <div class="content_wrapper container">
        <div class="content">
            <?= Alert::widget() ?>
            <?=$this->render('_header', [
                'dataProvider' => $dataProvider,
                'title' => Yii::t('common', 'Блог'),
            ])?>
            <?= ListView::widget([
                'id'           => 'blog-list-view',
                'dataProvider' => $dataProvider,
                'layout'       => "{items}{pager}",
                'itemView'     => '../blog/_item',
            ]) ?>
        </div>
        <div class="side">
            <div class="side_container">
                <?=$this->render('../layouts/_side_popular_posts')?>
                <?=$this->render('../layouts/_side_comments_list')?>
            </div>
        </div>
    </div>
</main>

