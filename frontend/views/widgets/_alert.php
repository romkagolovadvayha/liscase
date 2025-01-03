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
    <?php if (!$user->store): ?>
        <div class="widget_skindrops">
            <div class="widget_skindrops_title"><?=Yii::t('common', 'Вайп блок на сервере!')?></div>
            <div class="widget_skindrops_description"><?=Yii::t('common', 'Корзина будет доступна в 07:00 МСК!')?></div>
            <a href="/servers" class="widget_skindrops_link"><?=Yii::t('common', 'Подробнее о сервере')?></a>
        </div>
    <?php else: ?>
        <div class="widget_skindrops">
            <div class="widget_skindrops_title"><?=Yii::t('common', 'Вывод товаров доступен!')?></div>
            <div class="widget_skindrops_description"><?=Yii::t('common', 'Чтобы получить товары в игре перейдите по ссылке ниже!')?></div>
            <a href="/store" class="widget_skindrops_link"><?=Yii::t('common', 'Открыть корзину')?></a>
        </div>
    <?php endif; ?>
<?php endif; ?>