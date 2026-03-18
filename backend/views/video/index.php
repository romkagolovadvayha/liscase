<?php

use yii\helpers\Html;
use yii\widgets\ListView;
use backend\models\video\UserVideoSearch;

/** @var yii\web\View $this */
/** @var UserVideoSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Видео пользователей');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;
?>
<div class="video-index-page">
    <?= \frontend\widgets\Alert::widget() ?>

    <?= ListView::widget([
        'dataProvider' => $dataProvider,
        'itemView' => '_card',
        'layout' => "{items}\n<div class=\"video-index-pager\">{pager}</div>",
        'itemOptions' => ['class' => 'video-index-card-wrap', 'tag' => 'div'],
        'options' => ['class' => 'video-index-cards', 'tag' => 'div'],
        'emptyText' => '<div class="video-index-empty">' . Yii::t('common', 'Видео не найдено') . '</div>',
        'emptyTextOptions' => ['class' => 'video-index-empty-wrap'],
    ]) ?>
</div>

<style>
.video-index-page {
    padding: 16px 24px;
    background: hsl(0 0% 10% / 1);
    min-height: 100%;
}
.video-index-cards {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.video-index-card-wrap { margin: 0; }
.video-index-card {
    display: flex;
    flex-direction: column;
    background: hsl(0 0% 15% / 1);
    border: 1px solid hsl(0 0% 20% / 1);
    border-radius: 10px;
    overflow: hidden;
    min-height: 0;
}
.video-index-card__preview {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    background: hsl(0 0% 18% / 1);
    overflow: hidden;
}
.video-index-card__preview-link {
    position: absolute;
    inset: 0;
    display: block;
}
.video-index-card__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.video-index-card__no-photo {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: hsl(0 0% 50%);
    font-size: 14px;
}
.video-index-card__body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.video-index-card__name {
    font-weight: 600;
    font-size: 15px;
    color: #fff;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.video-index-card__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: hsl(0 0% 65%);
    margin-bottom: 6px;
}
.video-index-card__user {
    color: hsl(210 100% 60%);
    text-decoration: none;
}
.video-index-card__user:hover { text-decoration: underline; }
.video-index-card__type { font-size: 12px; color: hsl(0 0% 55%); margin-bottom: 6px; }
.video-index-card__date { font-size: 12px; color: hsl(0 0% 50%); margin-bottom: 12px; }
.video-index-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.video-index-card__actions .ds-btn { flex-shrink: 0; }
.video-index-empty-wrap { grid-column: 1 / -1; }
.video-index-empty { text-align: center; color: hsl(0 0% 55%); padding: 32px 16px; }
.video-index-pager { margin-top: 24px; display: flex; justify-content: center; flex-wrap: wrap; gap: 4px; }
.video-index-pager .pagination { margin: 0; }
.video-index-pager .page-link {
    min-width: 40px; min-height: 40px;
    display: inline-flex; align-items: center; justify-content: center;
    background: hsl(0 0% 20% / 1); color: #fff;
    border: 1px solid hsl(0 0% 15% / 1); border-radius: 6px; text-decoration: none;
}
.video-index-pager .page-link:hover { background: hsl(0 0% 28% / 1); }
.video-index-pager .page-item.active .page-link { background: hsl(200 70% 50% / 1); border-color: hsl(200 70% 50% / 1); }
</style>
