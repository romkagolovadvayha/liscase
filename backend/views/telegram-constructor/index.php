<?php

use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use common\helpers\HStrings;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var backend\models\TelegramConstructorSearch $searchModel */
/** @var int $countTelegramUsers */
/** @var int $countVkUsers */

$this->title = 'Рассылки сообщений';
$this->params['contentClass'] = 'content-no-padding';
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
$totalCount = $dataProvider->getTotalCount();
$hasFilters = trim((string)$searchModel->title) !== ''
    || !in_array((string)$searchModel->status, ['', 'all'], true)
    || trim((string)$searchModel->bot_id) !== '';
?>
<div class="mailing-page mailing-campaigns-page">
    <?= $this->render('_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-page-head mailing-page-head--overview">
        <div>
            <h1>Рассылки</h1>
            <p>Создайте черновик, затем проверьте сообщение и точное число получателей перед запуском.</p>
        </div>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новая рассылка', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
    </header>

    <section class="mailing-reach-bar" aria-label="Доступные получатели">
        <div class="mailing-reach-bar__label">
            <span>Доступно сейчас</span>
            <small>без заблокировавших бота</small>
        </div>
        <div class="mailing-reach-item">
            <i class="fa-brands fa-telegram" aria-hidden="true"></i>
            <span><strong><?= Yii::$app->formatter->asInteger($countTelegramUsers) ?></strong> Telegram</span>
            <?php if ($isAdmin): ?>
                <?= Html::a('<i class="fa-solid fa-rotate" aria-hidden="true"></i>', ['update-telegram-audience'], [
                    'class' => 'mailing-reach-refresh',
                    'title' => 'Проверить доступность Telegram-получателей',
                    'aria-label' => 'Обновить аудиторию Telegram',
                    'data' => ['confirm' => 'Запустить проверку доступности Telegram-получателей?', 'method' => 'post'],
                ]) ?>
            <?php endif; ?>
        </div>
        <div class="mailing-reach-item mailing-reach-item--vk">
            <i class="fa-brands fa-vk" aria-hidden="true"></i>
            <span><strong><?= Yii::$app->formatter->asInteger($countVkUsers) ?></strong> ВКонтакте</span>
            <?php if ($isAdmin): ?>
                <?= Html::a('<i class="fa-solid fa-rotate" aria-hidden="true"></i>', ['update-vk-audience'], [
                    'class' => 'mailing-reach-refresh',
                    'title' => 'Обновить аудиторию ВКонтакте',
                    'aria-label' => 'Обновить аудиторию ВКонтакте',
                    'data' => ['confirm' => 'Запустить обновление аудитории ВКонтакте? Это может занять время.', 'method' => 'post'],
                ]) ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="mailing-history" aria-labelledby="mailing-history-title">
        <header class="mailing-history__head">
            <div>
                <h2 id="mailing-history-title">История</h2>
                <span><?= Yii::$app->formatter->asInteger($totalCount) ?> <?= HStrings::pluralForm($totalCount, ['рассылка', 'рассылки', 'рассылок']) ?></span>
            </div>
        </header>

        <?php $form = ActiveForm::begin([
            'action' => ['index'],
            'method' => 'get',
            'enableClientValidation' => false,
            'options' => ['class' => 'mailing-list-filters', 'role' => 'search'],
        ]) ?>
            <?= $form->field($searchModel, 'title', ['template' => '{input}', 'options' => ['class' => 'mailing-list-filters__search']])
                ->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Найти по названию…', 'aria-label' => 'Поиск рассылки по названию']) ?>
            <?= $form->field($searchModel, 'status', ['template' => '{input}', 'options' => ['class' => 'mailing-list-filters__select']])
                ->dropDownList(ArrayHelper::merge(['all' => 'Все статусы'], TelegramConstructor::getStatusList()), ['class' => 'ds-select form-control', 'aria-label' => 'Статус рассылки']) ?>
            <?= $form->field($searchModel, 'bot_id', ['template' => '{input}', 'options' => ['class' => 'mailing-list-filters__select']])
                ->dropDownList(ArrayHelper::merge(['' => 'Все каналы'], TelegramConstructor::getBotList()), ['class' => 'ds-select form-control', 'aria-label' => 'Канал рассылки']) ?>
            <button type="submit" class="ds-btn ds-btn--secondary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Показать</button>
            <?php if ($hasFilters): ?>
                <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--ghost" aria-label="Сбросить фильтры"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Сбросить</a>
            <?php endif; ?>
        <?php ActiveForm::end() ?>

        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_telegram_constructor_card',
            'layout' => "{items}\n<div class=\"mailing-list-pager\">{pager}</div>",
            'itemOptions' => ['tag' => false],
            'options' => ['class' => 'mailing-campaign-list'],
            'emptyText' => $hasFilters
                ? 'По этим условиям рассылок нет. Сбросьте фильтры и попробуйте снова.'
                : 'Рассылок пока нет. Создайте первый безопасный черновик.',
            'emptyTextOptions' => ['class' => 'mailing-empty-state'],
        ]) ?>
    </section>
</div>
