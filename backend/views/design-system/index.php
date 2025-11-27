<?php

use yii\helpers\Html;
use yii\bootstrap4\Alert;

$this->title = 'Дизайн-система';
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Цветовая палитра -->
        <section class="mb-5">
            <h2 class="mb-4">Цветовая палитра</h2>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="ds-card">
                        <div class="ds-bg--primary p-3 mb-2" style="height: 100px; border-radius: 8px;"></div>
                        <strong>Primary Background</strong>
                        <div class="ds-text--muted small">$ds-bg-primary</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-card">
                        <div class="ds-bg--secondary p-3 mb-2" style="height: 100px; border-radius: 8px;"></div>
                        <strong>Secondary Background</strong>
                        <div class="ds-text--muted small">$ds-bg-secondary</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-card">
                        <div class="ds-bg--tertiary p-3 mb-2" style="height: 100px; border-radius: 8px;"></div>
                        <strong>Tertiary Background</strong>
                        <div class="ds-text--muted small">$ds-bg-tertiary</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-card">
                        <div style="background: #006c2e; height: 100px; border-radius: 8px;" class="p-3 mb-2"></div>
                        <strong>Success</strong>
                        <div class="ds-text--muted small">$ds-success</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Типографика -->
        <section class="mb-5">
            <h2 class="mb-4">Типографика</h2>
            <div class="ds-card">
                <h1>Заголовок H1</h1>
                <h2>Заголовок H2</h2>
                <h3>Заголовок H3</h3>
                <h4>Заголовок H4</h4>
                <h5>Заголовок H5</h5>
                <h6>Заголовок H6</h6>
                <p>Обычный текст параграфа. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                <p class="ds-text--secondary">Вторичный текст</p>
                <p class="ds-text--muted">Приглушенный текст</p>
                <p><strong>Жирный текст</strong></p>
                <p><em>Курсивный текст</em></p>
            </div>
        </section>

        <!-- Кнопки -->
        <section class="mb-5">
            <h2 class="mb-4">Кнопки</h2>
            <div class="ds-card">
                <div class="mb-3">
                    <h5 class="mb-3">Основные варианты</h5>
                    <div class="ds-flex ds-flex--gap-md flex-wrap">
                        <button class="ds-btn ds-btn--primary">Primary</button>
                        <button class="ds-btn ds-btn--success">Success</button>
                        <button class="ds-btn ds-btn--danger">Danger</button>
                    </div>
                </div>
                <div class="mb-3">
                    <h5 class="mb-3">Размеры</h5>
                    <div class="ds-flex ds-flex--gap-md flex-wrap align-items-center">
                        <button class="ds-btn ds-btn--primary ds-btn--sm">Small</button>
                        <button class="ds-btn ds-btn--primary">Normal</button>
                        <button class="ds-btn ds-btn--primary ds-btn--lg">Large</button>
                    </div>
                </div>
                <div class="mb-3">
                    <h5 class="mb-3">С иконками</h5>
                    <div class="ds-flex ds-flex--gap-md flex-wrap">
                        <button class="ds-btn ds-btn--primary">
                            <i class="fas fa-save"></i> Сохранить
                        </button>
                        <button class="ds-btn ds-btn--success">
                            <i class="fas fa-check"></i> Применить
                        </button>
                        <button class="ds-btn ds-btn--danger">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </div>
                </div>
                <div>
                    <h5 class="mb-3">Отключенные</h5>
                    <div class="ds-flex ds-flex--gap-md flex-wrap">
                        <button class="ds-btn ds-btn--primary" disabled>Disabled</button>
                        <button class="ds-btn ds-btn--success" disabled>Disabled</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Карточки -->
        <section class="mb-5">
            <h2 class="mb-4">Карточки</h2>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="ds-card">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Простая карточка</h5>
                        </div>
                        <div class="ds-card__body">
                            <p>Содержимое карточки. Здесь может быть любой контент.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="ds-card ds-card--hover">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Карточка с hover</h5>
                        </div>
                        <div class="ds-card__body">
                            <p>Наведите курсор на карточку, чтобы увидеть эффект.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="ds-card">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">С футером</h5>
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

        <!-- Формы -->
        <section class="mb-5">
            <h2 class="mb-4">Формы</h2>
            <div class="ds-card">
                <form>
                    <div class="ds-form-group">
                        <label class="ds-label">Обычное поле</label>
                        <input type="text" class="ds-input" placeholder="Введите текст">
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label ds-label--required">Обязательное поле</label>
                        <input type="text" class="ds-input" placeholder="Обязательное поле">
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label">Поле с ошибкой</label>
                        <input type="text" class="ds-input" value="Некорректное значение">
                        <div class="ds-form-group__error">Это поле содержит ошибку</div>
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label">Поле с подсказкой</label>
                        <input type="text" class="ds-input" placeholder="Введите email">
                        <div class="ds-form-group__help">Введите корректный email адрес</div>
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label">Отключенное поле</label>
                        <input type="text" class="ds-input" value="Недоступно" disabled>
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label">Textarea</label>
                        <textarea class="ds-input" rows="4" placeholder="Введите текст"></textarea>
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label">Select</label>
                        <select class="ds-input">
                            <option>Вариант 1</option>
                            <option>Вариант 2</option>
                            <option>Вариант 3</option>
                        </select>
                    </div>
                    <div class="ds-form-group">
                        <label>
                            <input type="checkbox"> Чекбокс
                        </label>
                    </div>
                    <div class="ds-form-group">
                        <label>
                            <input type="radio" name="radio" checked> Радио 1
                        </label>
                        <label>
                            <input type="radio" name="radio"> Радио 2
                        </label>
                    </div>
                </form>
            </div>
        </section>

        <!-- Бейджи -->
        <section class="mb-5">
            <h2 class="mb-4">Бейджи и метки</h2>
            <div class="ds-card">
                <div class="ds-flex ds-flex--gap-md flex-wrap align-items-center">
                    <span class="ds-badge ds-badge--success">Success</span>
                    <span class="ds-badge ds-badge--danger">Danger</span>
                    <span class="ds-badge ds-badge--warning">Warning</span>
                    <span class="ds-badge ds-badge--info">Info</span>
                </div>
            </div>
        </section>

        <!-- Алерты -->
        <section class="mb-5">
            <h2 class="mb-4">Алерты</h2>
            <div class="ds-alert ds-alert--success">
                <strong>Успех!</strong> Операция выполнена успешно.
            </div>
            <div class="ds-alert ds-alert--danger">
                <strong>Ошибка!</strong> Произошла ошибка при выполнении операции.
            </div>
            <div class="ds-alert ds-alert--warning">
                <strong>Внимание!</strong> Обратите внимание на эту информацию.
            </div>
            <div class="ds-alert ds-alert--info">
                <strong>Информация!</strong> Полезная информация для пользователя.
            </div>
        </section>

        <!-- Счетчики -->
        <section class="mb-5">
            <h2 class="mb-4">Счетчики</h2>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value">1,234</div>
                        <div class="ds-counter__label">Пользователей</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value">567</div>
                        <div class="ds-counter__label">Заказов</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value">89</div>
                        <div class="ds-counter__label">Активных</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value">12</div>
                        <div class="ds-counter__label">Новых</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Таблицы -->
        <section class="mb-5">
            <h2 class="mb-4">Таблицы</h2>
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
        </section>

        <!-- Пагинация -->
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

        <!-- Разделители -->
        <section class="mb-5">
            <h2 class="mb-4">Разделители</h2>
            <div class="ds-card">
                <p>Текст перед разделителем</p>
                <hr class="ds-divider">
                <p>Текст после разделителя</p>
            </div>
        </section>

        <!-- Утилиты -->
        <section class="mb-5">
            <h2 class="mb-4">Утилиты</h2>
            <div class="ds-card">
                <h5 class="mb-3">Цвета текста</h5>
                <p class="ds-text--primary">Primary текст</p>
                <p class="ds-text--secondary">Secondary текст</p>
                <p class="ds-text--muted">Muted текст</p>
                <p class="ds-text--success">Success текст</p>
                <p class="ds-text--danger">Danger текст</p>
                <p class="ds-text--warning">Warning текст</p>
                <p class="ds-text--info">Info текст</p>

                <h5 class="mb-3 mt-4">Фоны</h5>
                <div class="ds-bg--primary p-3 mb-2">Primary фон</div>
                <div class="ds-bg--secondary p-3 mb-2">Secondary фон</div>
                <div class="ds-bg--tertiary p-3">Tertiary фон</div>

                <h5 class="mb-3 mt-4">Flex утилиты</h5>
                <div class="ds-flex ds-flex--center ds-bg--secondary p-3 mb-2" style="height: 60px;">
                    Центрированный контент
                </div>
                <div class="ds-flex ds-flex--between ds-bg--secondary p-3" style="height: 60px;">
                    <span>Слева</span>
                    <span>Справа</span>
                </div>
            </div>
        </section>

        <!-- Модальные окна -->
        <section class="mb-5">
            <h2 class="mb-4">Модальные окна</h2>
            <div class="ds-card">
                <button type="button" class="ds-btn ds-btn--primary" data-toggle="modal" data-target="#exampleModal">
                    Открыть модальное окно
                </button>
            </div>
        </section>
    </div>
</div>

<!-- Модальное окно -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <header>
                <h4 id="exampleModalLabel">Пример модального окна</h4>
                <button type="button" class="ds-btn ds-btn--icon" data-dismiss="modal" aria-label="Close" style="background: transparent; color: var(--ds-text-primary, hsl(0 0% 94.9% / 1));">
                    <i class="fas fa-times"></i>
                </button>
            </header>
            <div class="modal-body">
                <p>Это пример модального окна в темной теме.</p>
            </div>
            <footer class="ds-flex ds-flex--gap-md">
                <button type="button" class="ds-btn ds-btn--primary" data-dismiss="modal">Закрыть</button>
                <button type="button" class="ds-btn ds-btn--success">Сохранить</button>
            </footer>
        </div>
    </div>
</div>

<style>
.design-system-page {
    padding: 20px;
}

.design-system-page section {
    margin-bottom: 3rem;
}

.design-system-page h2 {
    color: var(--ds-text-primary, hsl(0 0% 94.9% / 1));
    font-size: 1.875rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--ds-border-color, hsl(0 0% 15.3% / 1));
}

.design-system-page h5 {
    color: var(--ds-text-primary, hsl(0 0% 94.9% / 1));
    font-size: 1.125rem;
    font-weight: 500;
    margin-bottom: 1rem;
}
</style>

