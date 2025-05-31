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
$tabs = [
    [
        'category' => 'tgbot',
        'title' => 'Персональный бот',
    ],
    [
        'title' => 'Для оповещений',
        'items' => [
            [
                'category' => 'tgbotReport',
                'title' => 'Телеграм канал для репортов',
            ],
            [
                'category' => 'tgbotPaymentReport',
                'title' => 'Телеграм канал для финансовых отчетов',
            ],
            [
                'category' => 'tgbotPayments',
                'title' => 'Телеграм канал для оповещений о платежах',
            ],
            [
                'category' => 'tgbotAlert',
                'title' => 'Телеграм канал для прочих оповещений',
            ],
            [
                'category' => 'tgbotSupportAlert',
                'title' => 'Поддержка, оповещения о сообщениях',
            ],
        ],
    ],
];
?>
<div class="wrap800">
    <?=$this->render('tabs', ['tabs' => $tabs])?>
</div>