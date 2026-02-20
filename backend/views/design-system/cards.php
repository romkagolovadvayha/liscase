<?php

use yii\helpers\Html;

$this->title = 'Карточки - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Карточки';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Простая карточка -->
        <section class="mb-5">
            <h2 class="mb-4">Простая карточка</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="ds-card">
                        <div class="ds-card__body">
                            <p>Содержимое карточки. Здесь может быть любой контент.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Карточка с header -->
        <section class="mb-5">
            <h2 class="mb-4">Карточка с заголовком</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="ds-card">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Заголовок карточки</h5>
                        </div>
                        <div class="ds-card__body">
                            <p>Содержимое карточки с заголовком.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Карточка с footer -->
        <section class="mb-5">
            <h2 class="mb-4">Карточка с футером</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="ds-card">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Заголовок</h5>
                        </div>
                        <div class="ds-card__body">
                            <p>Карточка с футером для действий.</p>
                        </div>
                        <div class="ds-card__footer">
                            <button class="ds-btn ds-btn--primary ds-btn--sm">Действие</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Карточка с hover -->
        <section class="mb-5">
            <h2 class="mb-4">Карточка с hover эффектом</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="ds-card ds-card--hover">
                        <div class="ds-card__body">
                            <p>Наведите курсор на карточку, чтобы увидеть эффект.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
