<?php

use yii\helpers\Html;

/** @var common\models\medals\Medal[] $medals */
/** @var common\models\medals\UserMedal[] $assignments */

$this->title = 'Медали пользователей';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="medals-index-page">
    <?= \frontend\widgets\Alert::widget() ?>
    <section class="medals-admin-section">
        <h2>Начислить медаль</h2>
        <?= Html::beginForm(['award'], 'post', ['class' => 'medals-award-form']) ?>
        <?= Html::label('Пользователь', 'medal-award-user', ['class' => 'visually-hidden']) ?>
        <?= Html::textInput('user_query', '', ['id' => 'medal-award-user', 'class' => 'ds-input', 'placeholder' => 'ID, Steam ID или точный ник', 'required' => true]) ?>
        <?= Html::label('Медаль', 'medal-award-medal', ['class' => 'visually-hidden']) ?>
        <?= Html::dropDownList('medal_id', null, \yii\helpers\ArrayHelper::map($medals, 'id', 'name'), ['id' => 'medal-award-medal', 'class' => 'ds-select', 'prompt' => 'Выберите медаль', 'required' => true]) ?>
        <?= Html::label('Описание выдачи', 'medal-award-note', ['class' => 'visually-hidden']) ?>
        <?= Html::textInput('note', '', ['id' => 'medal-award-note', 'class' => 'ds-input', 'placeholder' => 'Описание выдачи (необязательно)']) ?>
        <?= Html::submitButton('Начислить', ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::endForm() ?>
    </section>

    <section class="medals-admin-section">
        <h2>Каталог</h2>
        <div class="medals-admin-grid">
            <?php foreach ($medals as $medal): ?>
                <article class="medals-admin-card">
                    <img src="<?= Html::encode($medal->getImageUrl()) ?>" alt="" width="72" height="72">
                    <div>
                        <strong><?= Html::encode($medal->name) ?></strong>
                        <p><?= Html::encode($medal->description ?: 'Без описания') ?></p>
                        <span class="ds-badge <?= $medal->is_active ? 'ds-badge--success' : 'ds-badge--secondary' ?>"><?= $medal->is_active ? 'Активна' : 'Скрыта' ?></span>
                    </div>
                    <?= Html::a('<i class="fas fa-pen" aria-hidden="true"></i>', ['update', 'id' => $medal->id], [
                        'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
                        'aria-label' => 'Редактировать медаль «' . $medal->name . '»',
                        'title' => 'Редактировать',
                    ]) ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="medals-admin-section">
        <h2>Последние выдачи</h2>
        <div class="medals-awards-list">
            <?php foreach ($assignments as $assignment): ?>
                <div class="medals-awards-row">
                    <img src="<?= Html::encode($assignment->medal->getImageUrl()) ?>" alt="" width="40" height="40">
                    <div><strong><?= Html::encode($assignment->medal->name) ?></strong><br><small><?= Html::encode($assignment->user->username) ?> · <?= Html::encode($assignment->source_type) ?> · <?= Html::encode($assignment->awarded_at) ?></small></div>
                    <?= Html::a('Отозвать', ['revoke', 'id' => $assignment->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['method' => 'post', 'confirm' => 'Отозвать медаль?']]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
