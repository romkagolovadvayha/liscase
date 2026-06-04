<?php

use backend\forms\tournament\TournamentForm;

/** @var yii\web\View $this */
/** @var TournamentForm $model */

$this->title = Yii::t('common', 'Новый турнир');
$this->params['contentClass'] = 'content-no-padding';
?>

<?= $this->render('_form', ['model' => $model]) ?>
