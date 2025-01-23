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
?>

<div class="settings-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <ul>
        <li>
            <a href="/settings/index?category=tinkoffpay">Тинькофф</a>
        </li>
        <li>
            <a href="/settings/index?category=trc20">TRC20</a>
        </li>
        <li>
            <a href="/settings/index?category=ton">TON COIN</a>
        </li>
    </ul>
</div>