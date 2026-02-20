<?php
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $steamLoginUrl */
/** @var string $siteUrl */

$this->title = Yii::t('common', 'Вход в админку');
?>
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden shadow-xl">
            <div class="px-6 py-5 text-center">
                <h1 class="text-lg font-semibold text-white uppercase tracking-wide mb-1">
                    <?= Yii::t('common', 'Панель управления') ?>
                </h1>
                <p class="text-gray-400 text-sm mb-6">
                    <?= Yii::t('common', 'Войдите через Steam, чтобы получить доступ в админку.') ?>
                </p>
                <a href="<?= Html::encode($steamLoginUrl) ?>" class="inline-flex items-center justify-center gap-2 bg-[#1b2838] hover:bg-[#2a475e] text-white font-medium px-6 py-3 rounded transition-colors no-underline">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.03 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-2.861 4.141c.004.063.01.124.01.189 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24 18.697 24 24 18.697 24 11.979 24 5.261 18.697 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.986 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.643.264-1.348-.005-1.991-.139-.335-.355-.635-.636-.869l-.663.895c.331.239.599.553.781.921.405.994-.017 2.116-1.011 2.521-1.008.41-2.15-.017-2.556-1.013a2.084 2.084 0 0 1 .034-1.96zm4.872-4.168h2.005v2.005h-2.005V14.04zm0 3.344h2.005v2.005h-2.005v-2.005zm3.344-3.344h2.005v2.005h-2.005V14.04zm0 3.344h2.005v2.005h-2.005v-2.005z"/>
                    </svg>
                    <?= Yii::t('common', 'Войти через Steam') ?>
                </a>
            </div>
            <div class="px-6 py-4 border-t border-[hsl(0_0%_15.3%_/_1)] text-center">
                <p class="text-gray-500 text-xs mb-2">
                    <?= Yii::t('common', 'Нет аккаунта или не хотите входить?') ?>
                </p>
                <a href="<?= Html::encode($siteUrl) ?>" class="text-gray-400 hover:text-white text-sm transition-colors no-underline">
                    <?= Yii::t('common', 'Перейти на сайт') ?> →
                </a>
            </div>
        </div>
    </div>
</div>
