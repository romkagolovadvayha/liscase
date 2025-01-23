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

$this->title = 'Настройки ботов';
?>

<div class="setting">
    <?=$this->render('form', ['category' => 'tgbot', 'title' => 'Персональный бот'])?>
    <?=$this->render('form', ['category' => 'tgbotReport', 'title' => 'Телеграм канал для репортов<'])?>
    <?=$this->render('form', ['category' => 'tgbotPaymentReport', 'title' => 'Телеграм канал для финансовых отчетов'])?>
    <?=$this->render('form', ['category' => 'tgbotPayments', 'title' => 'Телеграм канал для оповещений о платежах'])?>
    <?=$this->render('form', ['category' => 'tgbotAlert', 'title' => 'Телеграм канал для прочих оповещений'])?>
</div>