<?php

use common\helpers\HStrings;

/** @var $count int */
/** @var $audienceId string */

?>

<?= $count ?> <?= HStrings::pluralForm($count, ['получатель', 'получателя', 'получателей']) ?> (<a href="/telegram-constructor/audience?id=<?= (int)$audienceId ?>" target="_blank" rel="noopener">Подробнее</a>)
