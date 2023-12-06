<?php

/** @var yii\web\View $this */
/** @var array $range */

?>

<div class="user_range <?=$range['class']?>" title="<?=Yii::t('common', 'Пользователь написал более {PARAMS_COUNT_POSTS} постов.', ['PARAMS_COUNT_POSTS' => $range['posts'] - 1])?>">
    <?=$range['name']?>
</div>