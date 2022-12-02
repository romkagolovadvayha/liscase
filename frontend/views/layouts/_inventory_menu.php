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
            'label'   => Yii::t('common', "Профиль"),
            'url'     => '/user/profile',
            'active' => _checkActive('/user/profile'),
        ],
        [
            'label'   => Yii::t('common', "Мои вещи"),
            'url'     => '/user/inventory',
            'active' => _checkActive('/user/inventory'),
        ],
        [
            'label'   => Yii::t('common', "Партнерская программа"),
            'url'     => '/user/partner',
            'active' => _checkActive('/user/partner'),
        ],
        [
            'label'   => Yii::t('common', "Вывод"),
            'url'     => '/payout/index',
            'active' => $url == '/payout' || _checkActive('payout/index'),
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