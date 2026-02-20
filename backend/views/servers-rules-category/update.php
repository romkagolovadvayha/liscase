<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRulesCategory $model */

$this->title = Yii::t('common', 'Редактировать категорию: {name}', ['name' => $model->name]);
$this->params['contentClass'] = 'content-no-padding';
$this->params['showFilters'] = false;
?>
<div class="servers-rules-category-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
