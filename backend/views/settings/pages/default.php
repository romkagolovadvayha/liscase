<?php

use yii\helpers\Html;

/** @var string $category */
/** @var string|null $pageTitle */

if (!empty($pageTitle)) {
    $this->title = $pageTitle;
} else {
    $this->title = Yii::t('common', 'Настройки');
}
?>

<div class="settings-index-page w-full p-4 lg:p-6">
    <?= $this->render('form', ['category' => $category]) ?>

    <?php if ($category === 'maxSupport'): ?>
        <div class="mt-4 bg-[hsl(0_0%_11.8%_/_1)] p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-zinc-200">Webhook MAX</div>
                    <div class="mt-1 text-xs text-zinc-500">
                        Использует сохранённые access token и секрет. Настройки формы перед нажатием нужно сохранить.
                    </div>
                </div>
                <?= Html::beginForm(['/settings/register-max-webhook'], 'post', ['class' => 'shrink-0']) ?>
                    <?= Html::submitButton('Зарегистрировать / обновить webhook', [
                        'class' => 'ds-btn ds-btn--primary',
                    ]) ?>
                <?= Html::endForm() ?>
            </div>
        </div>
    <?php endif; ?>
</div>
