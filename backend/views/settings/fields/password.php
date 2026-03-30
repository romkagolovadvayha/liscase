<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

$inputId = 'site-setting-pwd-' . (int) $item->id;
?>
<div class="setting_items_item_block setting_items_item_block_password flex flex-col gap-2">
    <div class="flex items-center justify-between gap-2">
        <label class="text-xs font-medium text-zinc-400"><?= Html::encode($item->name) ?></label>
        <a href="<?= Url::to(['/settings/update', 'id' => $item->id]) ?>" class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm" title="<?= Yii::t('common', 'Редактировать') ?>"><i class="fas fa-pen"></i></a>
    </div>
    <span class="text-[10px] text-zinc-500 font-mono"><?= Html::encode($item->category) ?>_<?= Html::encode($item->code) ?></span>
    <div class="flex gap-2 items-stretch w-full">
        <div class="flex-1 min-w-0">
            <?= Html::input(
                'password',
                'settings[' . $item->id . ']',
                $item->value,
                [
                    'class' => 'ds-input form-control w-full',
                    'id' => $inputId,
                    'autocomplete' => 'new-password',
                    'spellcheck' => 'false',
                ]
            ) ?>
        </div>
        <div class="flex shrink-0 gap-1 items-center">
            <button
                type="button"
                class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm js-site-setting-pwd-toggle"
                data-input-id="<?= Html::encode($inputId) ?>"
                title="<?= Yii::t('common', 'Показать или скрыть') ?>"
                aria-label="<?= Yii::t('common', 'Показать или скрыть') ?>"
            ><i class="fas fa-eye"></i></button>
            <button
                type="button"
                class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm js-site-setting-pwd-copy"
                data-input-id="<?= Html::encode($inputId) ?>"
                title="<?= Yii::t('common', 'Скопировать') ?>"
                aria-label="<?= Yii::t('common', 'Скопировать') ?>"
            ><i class="fas fa-copy"></i></button>
        </div>
    </div>
</div>
<script>
(function () {
    if (window.__siteSettingPwdUi) return;
    window.__siteSettingPwdUi = true;
    document.body.addEventListener('click', function (e) {
        var t = e.target.closest('.js-site-setting-pwd-toggle');
        if (t) {
            var id = t.getAttribute('data-input-id');
            var inp = id ? document.getElementById(id) : null;
            if (!inp) return;
            inp.type = inp.type === 'password' ? 'text' : 'password';
            var icon = t.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
            return;
        }
        var c = e.target.closest('.js-site-setting-pwd-copy');
        if (c) {
            var cid = c.getAttribute('data-input-id');
            var inpc = cid ? document.getElementById(cid) : null;
            if (!inpc || !inpc.value) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(inpc.value).catch(function () {});
            } else {
                inpc.select();
                try { document.execCommand('copy'); } catch (err) {}
            }
        }
    });
})();
</script>
