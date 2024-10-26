<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */

$this->title = Yii::t('common', 'Новая жалоба');
?>
<?= $this->render('_form', [
    'model' => $model,
]) ?>
<div class="page_preloader" id="product-loader"></div>
