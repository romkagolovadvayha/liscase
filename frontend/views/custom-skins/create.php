<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \frontend\forms\buildings\BuildingForm $model */
/** @var \common\models\servers\Servers $server */

?>
<?= $this->render('_form', [
    'model' => $model,
    'server' => $server,
]) ?>