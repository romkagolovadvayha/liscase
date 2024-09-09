<?php

use yii\bootstrap5\Html;

/** @var \common\models\user\UserConfirmCode $userConfirmModel */

$this->title = Yii::t('common', 'Код активации');

?>

<div class="white-box">
    <div class="row">
        <div class="col-md-9 col-xs-12">
            <p class="text-bold"><?= Yii::t('common', 'Код активации персонального Telegram-бота'); ?>:</p>

            <h4>
                <?= $userConfirmModel->code; ?><br />

                <?= Html::a(Yii::t('common', 'Скопировать код'), 'javascript:;', [
                    'class' => 'btn btn-default waves-effect waves-light btn-clipboard',
                    'title' => Yii::t('common', 'Скопировать код'),
                    'data'  => [
                        'clipboard-text' => $userConfirmModel->code,
                        'message'        => Yii::t('common', 'Код скопирован в буфер обмена!'),
                    ],
                ]); ?>
            </h4>
            <div>&nbsp;</div>

            <?php
            $botLink = Html::a('@ProstojServerBot', 'https://t.me/ProstojServerBot', ['target' => '_blank']);
            ?>

            <p>
                <?= Yii::t('common', 'Скопируйте и вставьте это код в {botLink}', [
                    'botLink' => $botLink
                ])?>
            </p>
        </div>
    </div>
</div>