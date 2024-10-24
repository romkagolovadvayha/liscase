<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \frontend\forms\buildings\BuildingForm $model */

$this->title = Yii::t('common', 'Новая постройка');
?>
<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?php echo $this->render('@frontend/views/widgets/_alert'); ?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </div>
        </main>
    </div>
</div>