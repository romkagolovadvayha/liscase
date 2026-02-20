<?php

use yii\helpers\Html;

$this->title = 'Таблицы - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Таблицы';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Базовая таблица -->
        <section class="mb-5">
            <h2 class="mb-4">Базовая таблица</h2>
            <div class="ds-card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Элемент 1</td>
                                <td><span class="ds-badge ds-badge--success">Активен</span></td>
                                <td>2025-01-15</td>
                                <td>
                                    <button class="ds-btn ds-btn--primary ds-btn--sm">Редактировать</button>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Элемент 2</td>
                                <td><span class="ds-badge ds-badge--warning">Ожидание</span></td>
                                <td>2025-01-14</td>
                                <td>
                                    <button class="ds-btn ds-btn--primary ds-btn--sm">Редактировать</button>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Элемент 3</td>
                                <td><span class="ds-badge ds-badge--danger">Отклонен</span></td>
                                <td>2025-01-13</td>
                                <td>
                                    <button class="ds-btn ds-btn--primary ds-btn--sm">Редактировать</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
