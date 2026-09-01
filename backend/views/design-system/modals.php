<?php

use yii\helpers\Html;

$this->title = 'Модальные окна - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Модальные окна';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Пример модального окна -->
        <section class="mb-5">
            <h2 class="mb-4">Модальное окно</h2>
            <div class="ds-card">
                <button type="button" class="ds-btn ds-btn--primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                    Открыть модальное окно
                </button>
            </div>
        </section>
    </div>
</div>

<!-- Модальное окно -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <header>
                <h2 id="exampleModalLabel">Пример модального окна</h2>
                <button type="button" class="ds-btn ds-btn--icon" data-bs-dismiss="modal" aria-label="Закрыть окно">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </header>
            <div class="modal-body">
                <p>Это пример модального окна в темной теме.</p>
            </div>
            <footer class="ds-flex ds-flex--gap-md">
                <button type="button" class="ds-btn ds-btn--secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="ds-btn ds-btn--primary">Сохранить</button>
            </footer>
        </div>
    </div>
</div>
