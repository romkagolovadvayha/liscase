<?php

use yii\helpers\Html;
use yii\widgets\ListView;
use backend\models\serverskin\ServerSkinSearch;

/** @var yii\web\View $this */
/** @var ServerSkinSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Свои скины');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;
?>
<div class="server-skin-index-page">
    <?= \frontend\widgets\Alert::widget() ?>

    <?= ListView::widget([
        'dataProvider' => $dataProvider,
        'itemView' => '_card',
        'layout' => "{items}\n<div class=\"server-skin-index-pager\">{pager}</div>",
        'itemOptions' => ['class' => 'server-skin-index-card-wrap', 'tag' => 'div'],
        'options' => ['class' => 'server-skin-index-cards', 'tag' => 'div'],
        'emptyText' => '<div class="server-skin-index-empty">' . Yii::t('common', 'Скинов не найдено') . '</div>',
        'emptyTextOptions' => ['class' => 'server-skin-index-empty-wrap'],
    ]) ?>
</div>

<style>
.server-skin-index-page {
    padding: 16px 24px;
    background: hsl(0 0% 10% / 1);
    min-height: 100%;
}
.server-skin-index-cards {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.server-skin-index-card-wrap {
    margin: 0;
}
.server-skin-index-card {
    display: flex;
    flex-direction: column;
    background: hsl(0 0% 15% / 1);
    border: 1px solid hsl(0 0% 20% / 1);
    border-radius: 10px;
    overflow: hidden;
    min-height: 0;
}
.server-skin-index-card__preview {
    position: relative;
    width: 100%;
    padding-bottom: 75%;
    background: hsl(0 0% 18% / 1);
    overflow: hidden;
}
.server-skin-index-card__preview-link {
    position: absolute;
    inset: 0;
    display: block;
}
.server-skin-index-card__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.server-skin-index-card__no-photo {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: hsl(0 0% 50%);
    font-size: 14px;
}
.server-skin-index-card__body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.server-skin-index-card__name {
    font-weight: 600;
    font-size: 15px;
    color: #fff;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.server-skin-index-card__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: hsl(0 0% 65%);
    margin-bottom: 6px;
}
.server-skin-index-card__user {
    color: hsl(210 100% 60%);
    text-decoration: none;
}
.server-skin-index-card__user:hover {
    text-decoration: underline;
}
.server-skin-index-card__skin-id {
    font-size: 12px;
    color: hsl(0 0% 55%);
    margin-bottom: 6px;
}
.server-skin-index-card__date {
    font-size: 12px;
    color: hsl(0 0% 50%);
    margin-bottom: 12px;
}
.server-skin-index-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.server-skin-index-card__actions .ds-btn {
    flex-shrink: 0;
}
.server-skin-index-empty-wrap {
    grid-column: 1 / -1;
}
.server-skin-index-empty {
    text-align: center;
    color: hsl(0 0% 55%);
    padding: 32px 16px;
}
.server-skin-index-pager {
    margin-top: 24px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 4px;
}
.server-skin-index-pager .pagination {
    margin: 0;
}
.server-skin-index-pager .page-link {
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
.server-skin-index-pager .page-link:hover {
    background: hsl(0 0% 28% / 1);
}
.server-skin-index-pager .page-item.active .page-link {
    background: hsl(200 70% 50% / 1);
    border-color: hsl(200 70% 50% / 1);
}
</style>
