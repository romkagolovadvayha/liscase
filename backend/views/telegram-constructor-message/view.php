<?php

use backend\models\TelegramConstructorMessage;
use common\components\helpers\Role;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var TelegramConstructorMessage $model */

$this->title = $model->title ?: 'Шаблон #' . $model->id;
$this->params['contentClass'] = 'content-no-padding';
$usageCount = $model->getUsageCount();
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
?>
<div class="mailing-page mailing-template-view-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-review-head">
        <div>
            <div class="mailing-review-head__meta"><span>Шаблон #<?= (int)$model->id ?></span><span><?= Html::encode($model->getUsageLabel()) ?></span></div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>Сохранённая версия сообщения и связанные действия.</p>
        </div>
        <div class="mailing-review-head__actions">
            <?= Html::a('<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> К шаблонам', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a('<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Создать рассылку', ['/telegram-constructor/create'], ['class' => 'ds-btn ds-btn--success']) ?>
        </div>
    </header>

    <div class="mailing-template-view-grid">
        <section class="mailing-review-preview">
            <header><h2>Предпросмотр</h2><span>Русская версия</span></header>
            <?= $this->render('preview', ['model' => $model]) ?>
        </section>
        <aside class="mailing-review-section">
            <header><h2>Сведения</h2><span>Использование шаблона</span></header>
            <dl class="mailing-summary-list">
                <div><dt>Создан</dt><dd><?= Yii::$app->formatter->asDatetime($model->created_at, 'php:d.m.Y, H:i') ?></dd></div>
                <div><dt>Кнопок</dt><dd><?= count($model->telegramConstructorButtons) ?></dd></div>
                <div><dt>Используется</dt><dd><?= Html::encode($model->getUsageLabel()) ?></dd></div>
            </dl>
            <?php if ($isAdmin): ?>
            <div class="mailing-review-danger-zone mailing-review-danger-zone--stacked">
                <div>
                    <strong>Удаление шаблона</strong>
                    <span><?= $usageCount > 0 ? 'Недоступно: шаблон уже связан с рассылками.' : 'Шаблон ещё не использовался и может быть удалён.' ?></span>
                </div>
                <?php if ($usageCount > 0): ?>
                    <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" disabled><i class="fa-solid fa-lock" aria-hidden="true"></i> Используется</button>
                <?php else: ?>
                    <?= Html::a('<i class="fa-solid fa-trash" aria-hidden="true"></i> Удалить', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => 'Удалить шаблон?', 'method' => 'post']]) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
