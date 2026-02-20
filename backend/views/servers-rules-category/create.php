<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRulesCategory $model */

$this->title = Yii::t('common', 'Создать категорию правил');
$this->params['contentClass'] = 'content-no-padding';
$this->params['showFilters'] = false;
?>
<div class="servers-rules-category-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
