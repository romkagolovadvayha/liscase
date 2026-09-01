<?php

use backend\models\TelegramConstructor;
use backend\models\TelegramRecipients;
use common\helpers\HStrings;
use yii\helpers\Html;

/** @var TelegramRecipients $model */

$quantity = $model->getResolvedQuantity();
$isAutomatic = array_key_exists((string)$model->name, TelegramConstructor::getLkLanguagesArr());
?>
<article class="mailing-library-row">
    <a class="mailing-library-row__body" href="<?= Html::encode(yii\helpers\Url::to(['view', 'id' => $model->id])) ?>">
        <span class="mailing-library-row__thumb"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
        <span class="mailing-library-row__title">
            <strong><?= Html::encode($model->name) ?></strong>
            <small><?= $isAutomatic ? 'Автоматически обновляется по языку профиля' : 'Ручная выборка пользователей' ?></small>
        </span>
        <span class="mailing-library-row__meta">
            <strong><?= Yii::$app->formatter->asInteger($quantity) ?> <?= HStrings::pluralForm($quantity, ['получатель', 'получателя', 'получателей']) ?></strong>
            <small><?= Html::encode($model->getUsageLabel()) ?></small>
        </span>
    </a>
    <div class="mailing-library-row__actions">
        <?= Html::a('<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Использовать', ['/telegram-constructor/create', 'audience' => TelegramConstructor::CUSTOM_AUDIENCE_OFFSET + (int)$model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
        <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'aria-label' => 'Изменить аудиторию', 'title' => 'Изменить']) ?>
    </div>
</article>
