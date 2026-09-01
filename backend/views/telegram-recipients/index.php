<?php

use common\helpers\HStrings;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var backend\models\TelegramRecipientsSearch $searchModel */

$this->title = 'Аудитории рассылок';
$this->params['contentClass'] = 'content-no-padding';
$totalCount = $dataProvider->getTotalCount();
$hasFilter = trim((string)$searchModel->name) !== '';
?>
<div class="mailing-page mailing-audiences-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-page-head mailing-page-head--overview">
        <div>
            <h1>Аудитории</h1>
            <p>Сохранённые выборки пользователей. Доступность конкретного канала повторно проверяется перед отправкой.</p>
        </div>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новая аудитория', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
    </header>

    <section class="mailing-history" aria-labelledby="mailing-audience-list-title">
        <header class="mailing-history__head">
            <div>
                <h2 id="mailing-audience-list-title">Сохранённые аудитории</h2>
                <span><?= Yii::$app->formatter->asInteger($totalCount) ?> <?= HStrings::pluralForm($totalCount, ['аудитория', 'аудитории', 'аудиторий']) ?></span>
            </div>
        </header>

        <?php $form = ActiveForm::begin([
            'action' => ['index'],
            'method' => 'get',
            'enableClientValidation' => false,
            'options' => ['class' => 'mailing-list-filters mailing-list-filters--compact', 'role' => 'search'],
        ]) ?>
            <?= $form->field($searchModel, 'name', ['template' => '{input}', 'options' => ['class' => 'mailing-list-filters__search']])
                ->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Найти аудиторию…', 'aria-label' => 'Поиск аудитории по названию']) ?>
            <button type="submit" class="ds-btn ds-btn--secondary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Найти</button>
            <?php if ($hasFilter): ?>
                <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--ghost"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Сбросить</a>
            <?php endif; ?>
        <?php ActiveForm::end() ?>

        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_recipient_row',
            'layout' => "{items}\n<div class=\"mailing-list-pager\">{pager}</div>",
            'itemOptions' => ['tag' => false],
            'options' => ['class' => 'mailing-library-list'],
            'emptyText' => $hasFilter ? 'Аудитории с таким названием не найдены.' : 'Сохранённых аудиторий пока нет.',
            'emptyTextOptions' => ['class' => 'mailing-empty-state'],
        ]) ?>
    </section>
</div>
