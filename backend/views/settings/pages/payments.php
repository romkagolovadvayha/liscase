<?php

use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\Pjax;

/** @var string $category */

/** @var \common\models\site\SiteSetting[] $settings */
$settings = SiteSetting::find()
                       ->andWhere(['category' => $category])
                       ->indexBy('id')
                       ->all();

$this->title = 'Способы оплаты';
$tabs = [
    [
        'category' => 'tinkoffpay',
        'title' => 'Тинькофф',
    ],
    [
        'category' => 'trc20',
        'title' => 'TRC20',
    ],
    [
        'category' => 'ton',
        'title' => 'TON COIN',
    ],
];
?>
<div class="wrap800">
    <?=$this->render('tabs', ['tabs' => $tabs])?>
</div>