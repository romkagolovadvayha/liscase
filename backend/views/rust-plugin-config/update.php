<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfig $model */

$this->title = 'Изменить: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Конфиги плагинов Rust', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменить';
?>
<div class="rust-plugin-config-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

