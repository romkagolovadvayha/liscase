<?php

use yii\widgets\ListView;
use frontend\widgets\Alert;
use common\models\settings\Settings;

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Баги и новости Rust');

$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог")];
?>

<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?=$this->render('../layouts/_side_category_list')?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
            <!--            --><?php //echo $this->render('@frontend/views/widgets/_bonuses'); ?>
            <?= $this->render('@frontend/views/widgets/_banners'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
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
        </main>
    </div>
</div>
