<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $category */
/** @var array $categoryMeta */
/** @var array $navigation */
/** @var \common\models\site\SiteSetting[] $settings */
/** @var int $totalCount */
?>

<div class="settings-workspace" data-settings-workspace>
    <aside class="settings-navigation" aria-label="Разделы настроек">
        <div class="settings-navigation__intro">
            <div>
                <p class="settings-navigation__eyebrow">Центр управления</p>
                <h2>Все настройки</h2>
            </div>
            <span class="settings-navigation__total" title="Всего параметров"><?= (int) $totalCount ?></span>
            <button type="button"
                    class="ds-btn ds-btn--icon ds-btn--ghost settings-navigation__toggle"
                    aria-label="Показать разделы настроек"
                    aria-controls="settings-navigation-content"
                    aria-expanded="false"
                    data-settings-nav-toggle>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
        </div>

        <div id="settings-navigation-content" data-settings-nav-content>
        <label class="settings-search" for="settings-category-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <span class="visually-hidden">Найти раздел настроек</span>
            <input id="settings-category-search" type="search" placeholder="Найти раздел" autocomplete="off" data-settings-category-search>
            <kbd>⌘K</kbd>
        </label>

        <nav class="settings-navigation__groups">
            <?php foreach ($navigation as $group): ?>
                <section class="settings-navigation__group" data-settings-category-group>
                    <h3><i class="<?= Html::encode($group['icon']) ?>" aria-hidden="true"></i><?= Html::encode($group['label']) ?></h3>
                    <ul>
                        <?php foreach ($group['categories'] as $item): ?>
                            <?php $active = $item['code'] === $category; ?>
                            <li data-settings-category-item data-search-value="<?= Html::encode(mb_strtolower($item['label'] . ' ' . $item['code'])) ?>">
                                <a href="<?= Url::to(['/settings/index', 'category' => $item['code']]) ?>"
                                   class="settings-navigation__link<?= $active ? ' is-active' : '' ?>"
                                   <?= $active ? 'aria-current="page"' : '' ?>>
                                    <i class="<?= Html::encode($item['icon']) ?>" aria-hidden="true"></i>
                                    <span><?= Html::encode($item['label']) ?></span>
                                    <small><?= (int) $item['count'] ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </nav>
        <p class="settings-navigation__empty" data-settings-category-empty hidden>Разделы не найдены</p>
        </div>
    </aside>

    <section class="settings-panel" aria-labelledby="settings-page-title">
        <header class="settings-panel__header">
            <div class="settings-panel__heading">
                <span class="settings-panel__icon"><i class="<?= Html::encode($categoryMeta['icon']) ?>" aria-hidden="true"></i></span>
                <div>
                    <p class="settings-panel__eyebrow"><?= Html::encode($categoryMeta['code']) ?></p>
                    <h1 id="settings-page-title"><?= Html::encode($categoryMeta['label']) ?></h1>
                    <p><?= Html::encode($categoryMeta['description']) ?></p>
                </div>
            </div>

            <?php if (count($settings) > 6): ?>
                <label class="settings-field-search" for="settings-field-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span class="visually-hidden">Найти параметр в разделе</span>
                    <input id="settings-field-search" type="search" placeholder="Найти параметр" autocomplete="off" data-settings-field-search>
                </label>
            <?php endif; ?>
        </header>

        <div class="settings-panel__body">
            <?= $this->render('form', ['category' => $category, 'settings' => $settings]) ?>

            <?php if ($category === 'maxSupport'): ?>
                <section class="settings-integration-action" aria-labelledby="max-webhook-title">
                    <div>
                        <h3 id="max-webhook-title">Webhook MAX</h3>
                        <p>Сначала сохраните параметры выше, затем зарегистрируйте webhook.</p>
                    </div>
                    <?= Html::beginForm(['/settings/register-max-webhook'], 'post') ?>
                        <?= Html::submitButton('Обновить webhook', ['class' => 'ds-btn ds-btn--secondary']) ?>
                    <?= Html::endForm() ?>
                </section>
            <?php endif; ?>
        </div>
    </section>
</div>
