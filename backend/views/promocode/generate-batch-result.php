<?php

use yii\helpers\Html;

/** @var string[] $codes */
/** @var int $amount */

$this->title = Yii::t('common', 'Одноразовые промокоды созданы');
$codesText = implode("\n", $codes);
?>
<div class="promocode-generate-result p-4">
    <p class="text-gray-400 text-sm mb-2">Создано промокодов: <?= count($codes) ?>, сумма: <?= (int) $amount ?> RUB. Каждый промокод одноразовый и бессрочный.</p>
    <label class="block text-xs text-gray-400 mb-1">Список промокодов (по одному на строку):</label>
    <textarea id="promocode-codes-list" class="ds-input w-full font-mono text-sm" rows="<?= min(count($codes) + 2, 30) ?>" readonly><?= Html::encode($codesText) ?></textarea>
    <div class="flex gap-2 mt-4">
        <button type="button" class="ds-btn ds-btn--primary" id="copy-codes-btn"><i class="fas fa-copy"></i> Копировать</button>
        <?= Html::a(Yii::t('common', 'К списку'), ['/promocode/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>
</div>
<?php
$this->registerJs(<<<JS
(function(){
    var ta = document.getElementById('promocode-codes-list');
    var btn = document.getElementById('copy-codes-btn');
    if (btn && ta) {
        btn.addEventListener('click', function(){
            ta.select();
            document.execCommand('copy');
            btn.textContent = 'Скопировано';
            setTimeout(function(){ btn.innerHTML = '<i class=\"fas fa-copy\"></i> Копировать'; }, 2000);
        });
    }
})();
JS
);
?>
