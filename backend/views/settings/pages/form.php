<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var string $category */
/** @var \common\models\site\SiteSetting[] $settings */
?>

<div class="settings-form-wrap">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'settings-form-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $category),
        'options' => [
            'enctype' => 'multipart/form-data',
            'class' => 'settings-form',
            'data-settings-form' => true,
        ],
        'method' => 'post',
        'action' => Url::to(['/settings/form', 'category' => $category]),
    ]); ?>

    <?= \frontend\widgets\Alert::widget() ?>

    <?php if ($settings === []): ?>
        <div class="admin-empty-state">
            <span><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
            <h3>В этом разделе пока нет параметров</h3>
            <p>Добавьте первый параметр — после сохранения он сразу появится здесь.</p>
            <?= Html::a('Добавить параметр', ['/settings/create', 'category' => $category], ['class' => 'ds-btn ds-btn--primary']) ?>
        </div>
    <?php else: ?>
        <div class="settings-fields" data-settings-fields>
            <?php foreach ($settings as $setting): ?>
                <?= $this->render('_field', ['item' => $setting]) ?>
            <?php endforeach; ?>
        </div>

        <p class="settings-fields__empty" data-settings-fields-empty hidden>По запросу ничего не найдено.</p>

        <footer class="settings-savebar">
            <div class="settings-savebar__status" aria-live="polite">
                <span class="settings-savebar__dot" aria-hidden="true"></span>
                <span data-settings-save-status>Изменений нет</span>
            </div>
            <div class="settings-savebar__actions">
                <span><?= count($settings) ?> <?= count($settings) === 1 ? 'параметр' : 'параметров' ?></span>
                <?= Html::submitButton(
                    '<i class="fa-solid fa-check" aria-hidden="true"></i><span>Сохранить изменения</span>',
                    ['class' => 'ds-btn ds-btn--primary', 'data-settings-submit' => true]
                ) ?>
            </div>
        </footer>
    <?php endif; ?>

    <?php ActiveForm::end(); ?>
</div>
