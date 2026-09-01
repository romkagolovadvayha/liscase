<?php

use backend\models\TelegramConstructorMessage;
use yii\helpers\Html;
use yii\helpers\StringHelper;

/** @var TelegramConstructorMessage $model */

$imageUrl = $model->getPubUrl();
$excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags($model->getMessage())));
?>
<article class="mailing-library-row">
    <a class="mailing-library-row__body" href="<?= Html::encode(yii\helpers\Url::to(['view', 'id' => $model->id])) ?>">
        <span class="mailing-library-row__thumb">
            <?= $imageUrl
                ? Html::img($imageUrl, ['loading' => 'lazy', 'alt' => ''])
                : '<i class="fa-regular fa-message" aria-hidden="true"></i>' ?>
        </span>
        <span class="mailing-library-row__title">
            <strong><?= Html::encode($model->title ?: 'Шаблон #' . $model->id) ?></strong>
            <small><?= Html::encode($excerpt !== '' ? StringHelper::truncate($excerpt, 96) : 'Сообщение без текста') ?></small>
        </span>
        <span class="mailing-library-row__meta"><strong><?= Html::encode($model->getUsageLabel()) ?></strong><small><?= Yii::$app->formatter->asDatetime($model->created_at, 'php:d.m.Y, H:i') ?></small></span>
    </a>
    <div class="mailing-library-row__actions">
        <?= Html::a('<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Использовать', ['/telegram-constructor/create', 'template' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
        <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'aria-label' => 'Изменить шаблон', 'title' => 'Изменить']) ?>
    </div>
</article>
