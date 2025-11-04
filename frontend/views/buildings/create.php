<?php

use yii\helpers\Html;
use frontend\assets\BuildingsAsset;

/** @var yii\web\View $this */
/** @var \frontend\forms\buildings\BuildingForm $model */
/** @var \common\models\servers\Servers $server */

BuildingsAsset::register($this);

$this->title = Yii::t('common', 'Добавить постройку');
$this->registerMetaTag(['name' => 'description', 'content' => 'Поделитесь своей постройкой с сообществом Rust. Загрузите скриншоты, укажите местоположение и расскажите о своем творении.']);
?>

<!-- Хлебные крошки -->
<nav class="breadcrumbs" aria-label="breadcrumb">
    <?= Html::a(Yii::t('common', 'Главная'), ['/'], ['class' => 'breadcrumbs_item']) ?>
    <span class="breadcrumbs_separator">/</span>
    <?= Html::a(Yii::t('common', 'Постройки'), ['index'], ['class' => 'breadcrumbs_item']) ?>
    <span class="breadcrumbs_separator">/</span>
    <span class="breadcrumbs_item breadcrumbs_item--active"><?= $this->title ?></span>
</nav>

<div class="building-create">
    <!-- Заголовок с инфо -->
    <div class="building-create_hero">
        <div class="building-create_hero_content">
            <h1><?= Yii::t('common', 'Добавить постройку в галерею') ?></h1>
            <p><?= Yii::t('common', 'Поделитесь своим творением с сообществом! Ваша постройка появится в галерее после проверки модераторами.') ?></p>
        </div>
        <div class="building-create_hero_image">
            <i class="fa-solid fa-building"></i>
        </div>
    </div>

    <!-- Форма -->
    <?= $this->render('_form', [
        'model' => $model,
        'server' => $server,
    ]) ?>
</div>