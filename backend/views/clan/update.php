<?php

use common\models\clan\Clan;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Clan $model */
/** @var array<int, string> $leaderChoices */

$this->title = Yii::t('common', 'Редактировать клан') . ': ' . Html::encode($model->name);
$this->params['contentClass'] = 'content-no-padding';
$this->params['headerActions'] = [
    [
        'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
        'url' => ['view', 'id' => $model->id],
        'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
];
?>

<?= $this->render('_form', [
    'model' => $model,
    'leaderChoices' => $leaderChoices,
]) ?>
