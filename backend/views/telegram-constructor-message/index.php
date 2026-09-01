<?php

use common\helpers\HStrings;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var backend\models\TelegramConstructorMessageSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Шаблоны рассылок';
$this->params['contentClass'] = 'content-no-padding';
$totalCount = $dataProvider->getTotalCount();
$hasFilter = trim((string)$searchModel->title) !== '';
?>
<div class="mailing-page mailing-templates-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-page-head mailing-page-head--overview">
        <div>
            <h1>Шаблоны</h1>
            <p>Подготовленные сообщения для Telegram и ВКонтакте. Любой шаблон можно сразу использовать в новой рассылке.</p>
        </div>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новый шаблон', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
    </header>

    <section class="mailing-history" aria-labelledby="mailing-template-list-title">
        <header class="mailing-history__head">
            <div>
                <h2 id="mailing-template-list-title">Все шаблоны</h2>
                <span><?= Yii::$app->formatter->asInteger($totalCount) ?> <?= HStrings::pluralForm($totalCount, ['шаблон', 'шаблона', 'шаблонов']) ?></span>
            </div>
        </header>

        <?php $form = ActiveForm::begin([
            'action' => ['index'],
            'method' => 'get',
            'enableClientValidation' => false,
            'options' => ['class' => 'mailing-list-filters mailing-list-filters--compact', 'role' => 'search'],
        ]) ?>
            <?= $form->field($searchModel, 'title', ['template' => '{input}', 'options' => ['class' => 'mailing-list-filters__search']])
                ->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Найти шаблон…', 'aria-label' => 'Поиск шаблона по названию']) ?>
            <button type="submit" class="ds-btn ds-btn--secondary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Найти</button>
            <?php if ($hasFilter): ?>
                <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--ghost"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Сбросить</a>
            <?php endif; ?>
        <?php ActiveForm::end() ?>

        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_message_row',
            'layout' => "{items}\n<div class=\"mailing-list-pager\">{pager}</div>",
            'itemOptions' => ['tag' => false],
            'options' => ['class' => 'mailing-library-list'],
            'emptyText' => $hasFilter ? 'Шаблоны с таким названием не найдены.' : 'Шаблонов пока нет.',
            'emptyTextOptions' => ['class' => 'mailing-empty-state'],
        ]) ?>
    </section>
</div>
