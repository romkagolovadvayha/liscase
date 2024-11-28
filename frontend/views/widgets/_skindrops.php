<?php
    if (!Yii::$app->params['skindrops']) {
        return;
    }
    $user = Yii::$app->user->identity;
    $usernameCompleted = false;
    $prefix = "prostoj";
    if (!Yii::$app->user->isGuest && strpos(mb_strtolower($user->username), $prefix) !== false) {
        $usernameCompleted = true;
    }
    if (!$usernameCompleted) {
        return;
    }
    if ($user->userProfile->skindrops) {
        return;
    }
?>
<div class="widget_skindrops">
    <div class="widget_skindrops_title"><?=Yii::t('common', 'Раздача скинов')?></div>
    <div class="widget_skindrops_description"><?=Yii::t('common', 'Вы не участвуете в розыгрыше скинов!')?></div>
    <?php if (!empty($user->userProfile->skindrops_error)): ?>
        <div class="widget_skindrops_description_error"><?=$user->userProfile->skindrops_error?></div>
    <?php endif; ?>
    <a href="/skindrops" class="widget_skindrops_link"><?=Yii::t('common', 'Обновить трейд ссылку')?></a>
</div>