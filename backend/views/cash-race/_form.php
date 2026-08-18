<?php

use backend\forms\tournament\CashRaceForm;
use common\models\servers\Servers;
use common\models\tournament\Tournament;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var CashRaceForm $model */

$field = static function ($form, $model, string $attribute, array $options = []) {
    return $form->field($model, $attribute, array_merge([
        'options' => ['class' => 'cash-race-field'],
        'template' => '{label}{input}{hint}{error}',
    ], $options));
};

$servers = ArrayHelper::map(
    Servers::find()
        ->where(['status' => Servers::STATUS_ACTIVE])
        ->orderBy(['sort' => SORT_ASC])
        ->all(),
    'id',
    'name'
);

$rewardMeta = [
    ['place' => '1', 'name' => 'Первое место', 'tone' => 'gold', 'placeholder' => 'Золотая звезда'],
    ['place' => '2', 'name' => 'Второе место', 'tone' => 'silver', 'placeholder' => 'Серебряная звезда'],
    ['place' => '3', 'name' => 'Третье место', 'tone' => 'bronze', 'placeholder' => 'Бронзовая звезда'],
];
?>

<?php $form = ActiveForm::begin([
    'id' => 'cash-race-form',
    'options' => [
        'enctype' => 'multipart/form-data',
        'class' => 'cash-race-form',
    ],
]); ?>

<div class="cash-race-form__content scrollbar-thin">
    <div class="cash-race-form__intro">
        <span class="cash-race-form__intro-icon" aria-hidden="true"><i class="fas fa-key"></i></span>
        <div>
            <h2><?= $model->isNewRecord ? 'Новая денежная гонка' : Html::encode($model->title) ?></h2>
            <p>Настройте период турнира, выпадение ключей и работу терминала. Изменения попадут на сервер при следующем опросе API.</p>
        </div>
    </div>

    <section class="cash-race-section" aria-labelledby="cash-race-description-title">
        <header class="cash-race-section__header">
            <span class="cash-race-section__icon" aria-hidden="true"><i class="fas fa-align-left"></i></span>
            <div>
                <h3 id="cash-race-description-title">Описание и правила</h3>
                <p>Текст, который увидят игроки на странице турнира.</p>
            </div>
        </header>
        <div class="cash-race-section__body cash-race-section__body--copy">
            <?= $field($form, $model, 'title')->textInput([
                'class' => 'ds-input form-control',
                'maxlength' => true,
                'placeholder' => 'Например, Денежная гонка',
            ]) ?>
            <div class="cash-race-copy-grid">
                <?= $field($form, $model, 'description')->textarea([
                    'rows' => 5,
                    'class' => 'ds-textarea form-control',
                    'placeholder' => 'Кратко объясните цель турнира.',
                ]) ?>
                <?= $field($form, $model, 'rules_text')->textarea([
                    'rows' => 5,
                    'class' => 'ds-textarea form-control',
                    'placeholder' => 'Укажите правила регистрации ключей и определения победителей.',
                ]) ?>
            </div>
        </div>
    </section>

    <section class="cash-race-section" aria-labelledby="cash-race-keys-title">
        <header class="cash-race-section__header">
            <span class="cash-race-section__icon" aria-hidden="true"><i class="fas fa-drum-steelpan"></i></span>
            <div>
                <h3 id="cash-race-keys-title">Механика ключей</h3>
                <p>Вероятность выпадения, количество и игровой предмет ключа.</p>
            </div>
        </header>
        <div class="cash-race-section__body">
            <div class="cash-race-fields-grid cash-race-fields-grid--three">
                <?= $field($form, $model, 'drop_chance')
                    ->hint('0.12 означает шанс 12% с каждой подходящей бочки.')
                    ->input('number', [
                        'step' => '0.001',
                        'min' => 0,
                        'max' => 1,
                        'class' => 'ds-input form-control',
                    ])->label('Шанс выпадения (0–1)') ?>
                <?= $field($form, $model, 'drop_min')->input('number', [
                    'min' => 1,
                    'class' => 'ds-input form-control',
                ])->label('Минимум ключей') ?>
                <?= $field($form, $model, 'drop_max')->input('number', [
                    'min' => 1,
                    'class' => 'ds-input form-control',
                ])->label('Максимум ключей') ?>
            </div>
            <div class="cash-race-technical-grid">
                <?= $field($form, $model, 'key_shortname')->textInput([
                    'class' => 'ds-input form-control cash-race-mono-input',
                    'spellcheck' => 'false',
                ])->label('Rust shortname ключа') ?>
                <?= $field($form, $model, 'key_skin_id')->input('number', [
                    'min' => 0,
                    'class' => 'ds-input form-control cash-race-mono-input',
                ])->label('Skin ID ключа') ?>
                <?= $field($form, $model, 'preview_steam_id')->textInput([
                    'class' => 'ds-input form-control cash-race-mono-input',
                    'inputmode' => 'numeric',
                    'spellcheck' => 'false',
                ])->label('Steam ID приватного теста') ?>
            </div>
            <div class="cash-race-preview-toggle">
                <?= $field($form, $model, 'preview_only', [
                    'template' => '{input}{label}{hint}{error}',
                ])->checkbox([
                    'class' => 'cash-race-preview-toggle__input',
                    'label' => null,
                ], false)->label('Ограничить доступ к турниру')
                    ->hint('Раздел увидят только staff, серверные администраторы и указанный Steam ID.') ?>
            </div>
        </div>
    </section>

    <section class="cash-race-section" aria-labelledby="cash-race-terminal-title">
        <header class="cash-race-section__header">
            <span class="cash-race-section__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
            <div>
                <h3 id="cash-race-terminal-title">Терминал</h3>
                <p>Время активности и интервалы появления на случайной РТ.</p>
            </div>
        </header>
        <div class="cash-race-section__body">
            <div class="cash-race-fields-grid cash-race-fields-grid--three">
                <?= $field($form, $model, 'terminal_active_seconds')->input('number', [
                    'min' => 60,
                    'class' => 'ds-input form-control',
                ])->label('Активен, секунд') ?>
                <?= $field($form, $model, 'terminal_cooldown_min_seconds')->input('number', [
                    'min' => 60,
                    'class' => 'ds-input form-control',
                ])->label('Пауза минимум, секунд') ?>
                <?= $field($form, $model, 'terminal_cooldown_max_seconds')->input('number', [
                    'min' => 60,
                    'class' => 'ds-input form-control',
                ])->label('Пауза максимум, секунд') ?>
            </div>
            <?= $field($form, $model, 'terminal_prefab')->textInput([
                'class' => 'ds-input form-control cash-race-mono-input',
                'spellcheck' => 'false',
            ])->label('Prefab терминала') ?>
            <?= $field($form, $model, 'allowed_monuments_text')
                ->hint('Оставьте пустым, чтобы плагин автоматически использовал безопасные РТ.')
                ->textarea([
                    'rows' => 4,
                    'class' => 'ds-textarea form-control cash-race-mono-input',
                    'placeholder' => "airfield_1\nlaunch_site_1",
                    'spellcheck' => 'false',
                ])->label('Разрешённые РТ, по одной в строке') ?>
        </div>
    </section>
</div>

<aside class="cash-race-form__sidebar admin-filters-content scrollbar-thin">
    <div class="cash-race-form__sidebar-scroll">
        <section class="cash-race-sidebar-section" aria-labelledby="cash-race-launch-title">
            <header class="cash-race-sidebar-section__header">
                <span aria-hidden="true"><i class="fas fa-flag-checkered"></i></span>
                <div>
                    <h3 id="cash-race-launch-title">Запуск гонки</h3>
                    <p>Сервер, статус и точный период.</p>
                </div>
            </header>

            <label class="cash-race-field-label" for="cashraceform-server_id"><?= Html::encode($model->getAttributeLabel('server_id')) ?></label>
            <div class="ds-select-wrapper">
                <?= $field($form, $model, 'server_id', ['template' => '{input}{error}'])
                    ->dropDownList($servers, [
                        'class' => 'ds-select w-full',
                        'prompt' => 'Выберите сервер',
                    ]) ?>
                <i class="fas fa-chevron-down ds-select-arrow" aria-hidden="true"></i>
            </div>

            <label class="cash-race-field-label" for="cashraceform-status"><?= Html::encode($model->getAttributeLabel('status')) ?></label>
            <div class="ds-select-wrapper">
                <?= $field($form, $model, 'status', ['template' => '{input}{error}'])
                    ->dropDownList(Tournament::getStatusList(), ['class' => 'ds-select w-full']) ?>
                <i class="fas fa-chevron-down ds-select-arrow" aria-hidden="true"></i>
            </div>

            <div class="cash-race-date-grid">
                <?= $field($form, $model, 'starts_at')->input('datetime-local', [
                    'class' => 'ds-input form-control',
                ]) ?>
                <?= $field($form, $model, 'ends_at')->input('datetime-local', [
                    'class' => 'ds-input form-control',
                ]) ?>
            </div>
        </section>

        <section class="cash-race-sidebar-section" aria-labelledby="cash-race-prizes-title">
            <header class="cash-race-sidebar-section__header">
                <span aria-hidden="true"><i class="fas fa-trophy"></i></span>
                <div>
                    <h3 id="cash-race-prizes-title">Призовой фонд</h3>
                    <p>Награды для первых трёх мест.</p>
                </div>
            </header>

            <?= $field($form, $model, 'prize_pool_label')->textInput([
                'class' => 'ds-input form-control',
                'placeholder' => '30 000 ₽',
            ]) ?>

            <div class="cash-race-rewards">
                <?php foreach ($rewardMeta as $i => $meta): ?>
                    <div class="cash-race-reward cash-race-reward--<?= $meta['tone'] ?>">
                        <span class="cash-race-reward__place" aria-hidden="true"><?= $meta['place'] ?></span>
                        <div class="cash-race-reward__fields">
                            <strong><?= $meta['name'] ?></strong>
                            <?= $field($form, $model, "reward_title[$i]", ['template' => '{input}{error}'])
                                ->textInput([
                                    'class' => 'ds-input form-control',
                                    'placeholder' => '10 000 ₽',
                                    'aria-label' => $meta['name'] . ': основной приз',
                                ]) ?>
                            <?= $field($form, $model, "reward_subtitle[$i]", ['template' => '{input}{error}'])
                                ->textInput([
                                    'class' => 'ds-input form-control',
                                    'placeholder' => $meta['placeholder'],
                                    'aria-label' => $meta['name'] . ': дополнительная награда',
                                ]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="cash-race-form__actions">
        <?= Html::submitButton('<i class="fas fa-save" aria-hidden="true"></i> Сохранить', [
            'class' => 'ds-btn ds-btn--primary cash-race-form__save',
        ]) ?>
        <?= Html::a('Отмена', ['index'], [
            'class' => 'ds-btn ds-btn--secondary cash-race-form__cancel',
        ]) ?>

        <?php if (!$model->isNewRecord): ?>
            <?= Html::a('<i class="fas fa-users" aria-hidden="true"></i> Игроки и ключи', ['players', 'id' => $model->id], [
                'class' => 'ds-btn ds-btn--secondary cash-race-form__players',
            ]) ?>
            <div class="cash-race-form__runtime-actions">
                <?= Html::a('<i class="fas fa-play" aria-hidden="true"></i> Запустить сейчас', ['start', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--success',
                    'data-method' => 'post',
                ]) ?>
                <?= Html::a('<i class="fas fa-stop" aria-hidden="true"></i> Завершить', ['finish', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--danger',
                    'data-method' => 'post',
                    'data-confirm' => 'Рассчитать победителей и завершить гонку?',
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</aside>

<?php ActiveForm::end(); ?>
