<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfig $model */

$this->title = 'Добавить конфиг';
$this->params['breadcrumbs'][] = ['label' => 'Конфиги плагинов Rust', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rust-plugin-config-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

