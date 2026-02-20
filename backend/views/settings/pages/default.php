<?php

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
</div>