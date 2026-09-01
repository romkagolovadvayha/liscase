<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'О системе';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-about">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        Служебная страница административной панели.
    </p>

    <code><?= __FILE__ ?></code>
</div>
