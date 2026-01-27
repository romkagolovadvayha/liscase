<?php

/** @var int $serverId */

use yii\helpers\Html;

?>

<button 
    type="button" 
    class="year-review-button"
    data-server-id="<?= $serverId ?>"
    aria-label="<?= Yii::t('common', 'Итоги года') ?>"
>
    <span class="year-review-button__text"><?= Yii::t('common', 'ИТОГИ ГОДА') ?></span>
</button>

