<?php

use common\models\building\Building;
use yii\helpers\Html;
use yii\widgets\ListView;
use backend\models\building\BuildingSearch;

/** @var yii\web\View $this */
/** @var BuildingSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Постройки');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;
?>
<div class="building-index-page">
    <?= \frontend\widgets\Alert::widget() ?>

    <?= ListView::widget([
        'dataProvider' => $dataProvider,
        'itemView' => '_card',
        'layout' => "{items}\n<div class=\"building-index-pager\">{pager}</div>",
        'itemOptions' => ['class' => 'building-index-card-wrap', 'tag' => 'div'],
        'options' => ['class' => 'building-index-cards', 'tag' => 'div'],
        'emptyText' => '<div class="building-index-empty">' . Yii::t('common', 'Построек не найдено') . '</div>',
        'emptyTextOptions' => ['class' => 'building-index-empty-wrap'],
    ]) ?>
</div>

<style>
.building-index-page {
    padding: 16px 24px;
    background: hsl(0 0% 10% / 1);
    min-height: 100%;
}
.building-index-cards {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.building-index-card-wrap {
    margin: 0;
}
.building-index-card {
    display: flex;
    flex-direction: column;
    background: hsl(0 0% 15% / 1);
    border: 1px solid hsl(0 0% 20% / 1);
    border-radius: 10px;
    overflow: hidden;
    min-height: 0;
}
.building-index-card__gallery {
    position: relative;
    width: 100%;
    padding-bottom: 75%;
    background: hsl(0 0% 18% / 1);
    overflow: hidden;
}
.building-index-card__slides {
    position: absolute;
    inset: 0;
}
.building-index-card__slide {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: hsl(0 0% 18% / 1);
}
.building-index-card__slide--active {
    display: flex;
}
.building-index-card__slide-link {
    display: block;
    width: 100%;
    height: 100%;
}
.building-index-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.building-index-card__no-photo {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: hsl(0 0% 50%);
    font-size: 14px;
}
.building-index-card__nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(0,0,0,0.5);
    border: none;
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    transition: background 0.2s;
}
.building-index-card__nav:hover {
    background: rgba(0,0,0,0.7);
}
.building-index-card__nav--prev { left: 8px; }
.building-index-card__nav--next { right: 8px; }
.building-index-card__nav i { font-size: 12px; }
.building-index-card__dots {
    position: absolute;
    bottom: 8px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 6px;
    z-index: 2;
}
.building-index-card__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.4);
    cursor: pointer;
    padding: 0;
    transition: background 0.2s;
}
.building-index-card__dot:hover {
    background: rgba(255,255,255,0.6);
}
.building-index-card__dot--active {
    background: #fff;
}
.building-index-card__body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.building-index-card__name {
    font-weight: 600;
    font-size: 15px;
    color: #fff;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.building-index-card__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: hsl(0 0% 65%);
    margin-bottom: 6px;
}
.building-index-card__user {
    color: hsl(210 100% 60%);
    text-decoration: none;
}
.building-index-card__user:hover {
    text-decoration: underline;
}
.building-index-card__server {
    color: hsl(0 0% 60%);
}
.building-index-card__date {
    font-size: 12px;
    color: hsl(0 0% 50%);
    margin-bottom: 12px;
}
.building-index-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.building-index-card__actions .ds-btn {
    flex-shrink: 0;
}
.building-index-empty-wrap {
    grid-column: 1 / -1;
}
.building-index-empty {
    text-align: center;
    color: hsl(0 0% 55%);
    padding: 32px 16px;
}
.building-index-pager {
    margin-top: 24px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 4px;
}
.building-index-pager .pagination {
    margin: 0;
}
.building-index-pager .page-link {
    min-width: 40px;
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: hsl(0 0% 20% / 1);
    color: #fff;
    border: 1px solid hsl(0 0% 15% / 1);
    border-radius: 6px;
    text-decoration: none;
}
.building-index-pager .page-link:hover {
    background: hsl(0 0% 28% / 1);
}
.building-index-pager .page-item.active .page-link {
    background: hsl(200 70% 50% / 1);
    border-color: hsl(200 70% 50% / 1);
}
</style>

<script>
(function() {
    function initBuildingCardGalleries() {
        document.querySelectorAll('.building-index-card__gallery').forEach(function(gallery) {
            var slidesWrap = gallery.querySelector('.building-index-card__slides');
            if (!slidesWrap) return;
            var slideEls = gallery.querySelectorAll('.building-index-card__slide');
            var total = slideEls.length;
            if (total <= 1) return;

            var dots = gallery.querySelectorAll('.building-index-card__dot');
            var prevBtn = gallery.querySelector('.building-index-card__nav--prev');
            var nextBtn = gallery.querySelector('.building-index-card__nav--next');

            function show(index) {
                index = (index + total) % total;
                slidesWrap.setAttribute('data-current', index);
                for (var i = 0; i < slideEls.length; i++) {
                    slideEls[i].classList.toggle('building-index-card__slide--active', i === index);
                }
                for (var j = 0; j < dots.length; j++) {
                    dots[j].classList.toggle('building-index-card__dot--active', j === index);
                }
            }

            if (prevBtn) prevBtn.addEventListener('click', function() {
                show(parseInt(slidesWrap.getAttribute('data-current'), 10) - 1);
            });
            if (nextBtn) nextBtn.addEventListener('click', function() {
                show(parseInt(slidesWrap.getAttribute('data-current'), 10) + 1);
            });
            for (var k = 0; k < dots.length; k++) {
                (function(idx) {
                    dots[idx].addEventListener('click', function() { show(idx); });
                })(k);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBuildingCardGalleries);
    } else {
        initBuildingCardGalleries();
    }
})();
</script>
