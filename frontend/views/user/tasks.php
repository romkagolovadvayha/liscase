<?php

use yii\web\View;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Задания на вайп") . " - {$user->userProfile->name}";
?>

<?= Alert::widget() ?>
<div class="achievements_wrap">
    <?= $this->render('blocks/_achievements'); ?>
</div>
