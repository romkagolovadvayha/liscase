<?php

$this->registerJs(<<<JS
        $('.banners_widget').slick({
            infinite: true,
            dots: false,
            arrows: true,
            slidesToShow: 1,
            adaptiveHeight: true,
            autoplay: true,
            autoplaySpeed: 8000,
        });
JS
);
?>
<div class="banners_widget">
    <div>
        <div class="widget_banner">
            <div class="widget_banner_title"><?=Yii::t('common', 'Бонус WarGM')?></div>
            <div class="widget_banner_desctiption"><?=Yii::t('common', 'Голосуйте за наш сервер и получайте бонусы!')?></div>
            <a rel="nofollow" href="https://wargm.ru/server/70688/votes" class="widget_banner_link"><?=Yii::t('common', 'Голосовать')?></a>
        </div>
    </div>
    <div>
        <div class="widget_banner">
            <div class="widget_banner_title"><?=Yii::t('common', 'Как получать скины')?></div>
            <div class="widget_banner_desctiption"><?=Yii::t('common', 'Хотите получать скины в Steam бесплатно?')?></div>
            <a href="/skindrops" class="widget_banner_link"><?=Yii::t('common', 'Указать трейд ссылку')?></a>
        </div>
    </div>
</div>