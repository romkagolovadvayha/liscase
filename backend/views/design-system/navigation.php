<?php

use yii\helpers\Html;

$this->title = 'Навигация - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Навигация';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Breadcrumbs -->
        <section class="mb-5">
            <h2 class="mb-4">Хлебные крошки</h2>
            <div class="ds-card">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">Главная</a></li>
                        <li class="breadcrumb-item"><a href="#">Раздел</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Текущая страница</li>
                    </ol>
                </nav>
            </div>
        </section>

        <!-- Tabs -->
        <section class="mb-5">
            <h2 class="mb-4">Вкладки (Tabs)</h2>
            <div class="ds-card">
                <div class="ds-tabs">
                    <a href="#" class="ds-tabs__item ds-tabs__item--active">Вкладка 1</a>
                    <a href="#" class="ds-tabs__item">Вкладка 2</a>
                    <a href="#" class="ds-tabs__item">Вкладка 3</a>
                </div>
                <div class="ds-tab-content">
                    <p>Содержимое активной вкладки</p>
                </div>
            </div>
        </section>

        <!-- Pagination -->
        <section class="mb-5">
            <h2 class="mb-4">Пагинация</h2>
            <div class="ds-card">
                <ul class="pagination">
                    <li><a href="#">«</a></li>
                    <li><a href="#">1</a></li>
                    <li class="active"><a href="#">2</a></li>
                    <li><a href="#">3</a></li>
                    <li><a href="#">4</a></li>
                    <li><a href="#">5</a></li>
                    <li><a href="#">»</a></li>
                </ul>
            </div>
        </section>
    </div>
</div>
