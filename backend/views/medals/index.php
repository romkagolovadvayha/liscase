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
        <?= Html::textInput('user_query', '', ['class' => 'ds-input', 'placeholder' => 'ID, Steam ID или точный ник', 'required' => true]) ?>
        <?= Html::dropDownList('medal_id', null, \yii\helpers\ArrayHelper::map($medals, 'id', 'name'), ['class' => 'ds-select', 'prompt' => 'Выберите медаль', 'required' => true]) ?>
        <?= Html::textInput('note', '', ['class' => 'ds-input', 'placeholder' => 'Описание выдачи (необязательно)']) ?>
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
                    <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $medal->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
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
<style>
.medals-index-page{padding:16px 24px;background:hsl(0 0% 10%);min-height:100%}.medals-admin-section{background:hsl(0 0% 15%);border-radius:10px;padding:18px;margin-bottom:16px}.medals-admin-section h2{font-size:15px;color:#fff;margin:0 0 14px}.medals-award-form{display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) minmax(220px,2fr) auto;gap:10px}.medals-admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}.medals-admin-card,.medals-awards-row{display:flex;align-items:center;gap:12px;background:hsl(0 0% 19%);border-radius:8px;padding:12px}.medals-admin-card img,.medals-awards-row img{object-fit:contain;flex:none}.medals-admin-card>div,.medals-awards-row>div{min-width:0;flex:1}.medals-admin-card p{font-size:12px;color:hsl(0 0% 65%);margin:4px 0 8px}.medals-awards-list{display:grid;gap:8px}.medals-awards-row small{color:hsl(0 0% 60%)}@media(max-width:900px){.medals-award-form{grid-template-columns:1fr}}
</style>
