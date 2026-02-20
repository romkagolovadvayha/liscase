<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRules $model */

$this->title = Yii::t('common', 'Редактировать правило: {name}', ['name' => $model->title ?: '#' . $model->id]);
$this->params['contentClass'] = 'content-no-padding';
$this->params['showFilters'] = false;
?>
<div class="servers-rules-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
