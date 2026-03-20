<?php

use common\models\clan\Clan;

/** @var yii\web\View $this */
/** @var Clan $model */
/** @var array<int, string> $leaderChoices */

$this->title = Yii::t('common', 'Новый клан');
$this->params['contentClass'] = 'content-no-padding';
?>

<?= $this->render('_form', [
    'model' => $model,
    'leaderChoices' => $leaderChoices,
]) ?>
