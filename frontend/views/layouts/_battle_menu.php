<?php
    $url = Yii::$app->request->url;
    function _checkActive($urlStr)
    {
        return (bool)strstr(Yii::$app->request->url, $urlStr);
    }
?>
<?=\yii\bootstrap5\Nav::widget([
    'items' => [
        [
            'label'   => Yii::t('common', "Сражения"),
            'url'     => '/battle/index',
            'active' => _checkActive('/battle/index'),
        ],
        [
            'label'   => Yii::t('common', "Архив сражений"),
            'url'     => '/battle/archive',
            'active' => _checkActive('/battle/archive'),
        ],
        [
            'label'   => Yii::t('common', "Создать сражение"),
            'url'     => '/battle/create',
            'active' => _checkActive('/battle/create'),
        ],
    ],
    'options' => ['class' =>'nav nav-tabs user-nav-light'],
]);
?>
<!--<nav>-->
<!--    <div class="nav nav-tabs user-nav-light" id="nav-tab" role="tablist">-->
<!--        <li class="nav-link active"><a href="/user/inventory">Мои вещи</a></li>-->
<!--        <li class="nav-link"><a href="/user/boxes">Кейсы</a></li>-->
<!--        <li class="nav-link"><a href="/payout/index">Вывод</a></li>-->
<!--    </div>-->
<!--</nav>-->