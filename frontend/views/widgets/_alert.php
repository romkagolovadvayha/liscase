<?php
$user = null;
if (!Yii::$app->user->isGuest) {
    $user = Yii::$app->user->identity;
}
?>
<?php if (strtotime('2024-07-26 16:00:00') > time()): ?>
<div class="widget_skindrops">
    <div class="widget_skindrops_title"><?=Yii::t('common', 'Информация о ближайшем вайпе')?></div>
    <div class="widget_skindrops_description"><?=Yii::t('common', 'Вайп на сервере X2 уже сегодня в 16:00 МСК!')?></div>
    <a href="/servers" class="widget_skindrops_link"><?=Yii::t('common', 'Подробнее о сервере')?></a>
</div>
<?php endif; ?>

<?php if (!empty($user) && !empty($user->server) && !$user->server->is_store): ?>
<div class="widget_skindrops">
    <div class="widget_skindrops_title"><?=Yii::t('common', 'Вы играете на сервере без доната!')?></div>
    <div class="widget_skindrops_description"><?=Yii::t('common', 'Корзина на сервере не доступна!')?></div>
    <a href="/servers" class="widget_skindrops_link"><?=Yii::t('common', 'Подробнее о сервере')?></a>
</div>
<?php endif; ?>