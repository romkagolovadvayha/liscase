<?php

use yii\base\BaseObject;
use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Скиндропс");

$list = Yii::$app->rustTm->history()['data'];

$totals = [];
foreach ($list as $item) {
    $date = gmdate("Y-m-d", $item['time']);
    if ($item['stage'] != 2) {
        continue;
    }
    if (empty($totals[$date])) {
        if (count($totals) > 4) {
            break;
        }
        $totals[$date] = 0;
    }
    $totals[$date] += $item['paid'] / 100;
}

?>

<table class="kv-grid-table table table-bordered table-striped kv-table-wrap">
    <thead>
    <tr>
        <th>Дата</th>
        <th>Сумма</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($totals as $date => $total): ?>
        <tr>
            <td><?=$date?></td>
            <td><?=$total?> RUB</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
