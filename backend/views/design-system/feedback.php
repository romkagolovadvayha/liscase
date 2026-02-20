<?php

use yii\helpers\Html;

$this->title = 'Обратная связь - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Обратная связь';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Alerts -->
        <section class="mb-5">
            <h2 class="mb-4">Алерты</h2>
            <div class="ds-alert ds-alert--success">
                <div class="ds-alert__icon"><i class="fas fa-check-circle"></i></div>
                <div class="ds-alert__content">
                    <div class="ds-alert__title">Успех!</div>
                    <div class="ds-alert__message">Операция выполнена успешно.</div>
                </div>
            </div>
            <div class="ds-alert ds-alert--danger">
                <div class="ds-alert__icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="ds-alert__content">
                    <div class="ds-alert__title">Ошибка!</div>
                    <div class="ds-alert__message">Произошла ошибка при выполнении операции.</div>
                </div>
            </div>
            <div class="ds-alert ds-alert--warning">
                <div class="ds-alert__icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="ds-alert__content">
                    <div class="ds-alert__title">Внимание!</div>
                    <div class="ds-alert__message">Обратите внимание на эту информацию.</div>
                </div>
            </div>
            <div class="ds-alert ds-alert--info">
                <div class="ds-alert__icon"><i class="fas fa-info-circle"></i></div>
                <div class="ds-alert__content">
                    <div class="ds-alert__title">Информация!</div>
                    <div class="ds-alert__message">Полезная информация для пользователя.</div>
                </div>
            </div>
        </section>

        <!-- Badges -->
        <section class="mb-5">
            <h2 class="mb-4">Бейджи</h2>
            <div class="ds-card">
                <div class="ds-flex ds-flex-wrap ds-gap-3">
                    <span class="ds-badge ds-badge--success">Success</span>
                    <span class="ds-badge ds-badge--danger">Danger</span>
                    <span class="ds-badge ds-badge--warning">Warning</span>
                    <span class="ds-badge ds-badge--info">Info</span>
                    <span class="ds-badge ds-badge--primary">Primary</span>
                    <span class="ds-badge ds-badge--secondary">Secondary</span>
                </div>
            </div>
        </section>

        <!-- Progress Bar -->
        <section class="mb-5">
            <h2 class="mb-4">Progress Bar</h2>
            <div class="ds-card">
                <div class="ds-progress mb-3">
                    <div class="ds-progress__bar" style="width: 25%"></div>
                </div>
                <div class="ds-progress mb-3">
                    <div class="ds-progress__bar ds-progress__bar--success" style="width: 50%"></div>
                </div>
                <div class="ds-progress mb-3">
                    <div class="ds-progress__bar ds-progress__bar--danger" style="width: 75%"></div>
                </div>
                <div class="ds-progress">
                    <div class="ds-progress__bar ds-progress__bar--warning ds-progress__bar--striped" style="width: 100%"></div>
                </div>
            </div>
        </section>

        <!-- Loading Spinner -->
        <section class="mb-5">
            <h2 class="mb-4">Loading Spinner</h2>
            <div class="ds-card">
                <div class="ds-flex ds-items-center ds-gap-4">
                    <div class="ds-spinner ds-spinner--sm"></div>
                    <div class="ds-spinner"></div>
                    <div class="ds-spinner ds-spinner--lg"></div>
                    <div class="ds-spinner ds-spinner--primary"></div>
                    <div class="ds-spinner ds-spinner--success"></div>
                </div>
            </div>
        </section>

        <!-- Empty State -->
        <section class="mb-5">
            <h2 class="mb-4">Empty State</h2>
            <div class="ds-card">
                <div class="ds-empty-state">
                    <div class="ds-empty-state__icon"><i class="fas fa-inbox"></i></div>
                    <div class="ds-empty-state__title">Нет данных</div>
                    <div class="ds-empty-state__message">Здесь пока нет элементов для отображения</div>
                    <button class="ds-btn ds-btn--primary">Добавить элемент</button>
                </div>
            </div>
        </section>
    </div>
</div>
