<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRules $model */

$this->title = Yii::t('common', 'Создать правило');
$this->params['contentClass'] = 'content-no-padding';
$this->params['showFilters'] = false;
?>
<div class="servers-rules-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
