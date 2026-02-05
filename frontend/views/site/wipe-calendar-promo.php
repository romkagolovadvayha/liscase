<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */

$skins = [
    [
        'name' => 'Cookie Maker Furnace',
        'image' => 'https://cdn.rust.tm/item/Cookie%20Maker%20Furnace/300.png',
    ],
    [
        'name' => 'Advent Calendar Garage Door',
        'image' => 'https://cdn.rust.tm/item/Advent%20Calendar%20Garage%20Door/300.png',
    ],
    [
        'name' => 'Blueprint Metal Door',
        'image' => 'https://cdn.rust.tm/item/Blueprint%20Metal%20Door/300.png',
    ],
];

$randomSkin = $skins[array_rand($skins)];
?>

<div class="wipe-calendar-promo">
    <!-- Первый экран -->
    <div class="wipe-calendar-promo__screen wipe-calendar-promo__screen-1" id="wipe-calendar-promo-screen-1">
        <div class="wipe-calendar-promo__content">
            <div class="wipe-calendar-promo__header">
                <h2 class="wipe-calendar-promo__title"><?= Yii::t('common', 'Поздравляем!') ?></h2>
                <p class="wipe-calendar-promo__subtitle"><?= Yii::t('common', 'Вы выиграли скин!') ?></p>
            </div>

            <div class="wipe-calendar-promo__skin">
                <img src="<?= Html::encode($randomSkin['image']) ?>" alt="<?= Html::encode($randomSkin['name']) ?>" class="wipe-calendar-promo__skin-image">
                <p class="wipe-calendar-promo__skin-name"><?= Html::encode($randomSkin['name']) ?></p>
            </div>

            <div class="wipe-calendar-promo__actions">
                <div class="wipe-calendar-promo__buttons">
                    <button type="button" class="button-secondary" id="wipe-calendar-promo-close-btn-1">
                        <span class="button__text"><?= Yii::t('common', 'Закрыть') ?></span>
                    </button>
                    <button type="button" class="button-primary" id="wipe-calendar-promo-get-btn">
                        <span class="button__text"><?= Yii::t('common', 'Забрать скин') ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Второй экран -->
    <div class="wipe-calendar-promo__screen wipe-calendar-promo__screen-2" id="wipe-calendar-promo-screen-2" style="display: none;">
        <div class="wipe-calendar-promo__content">
            <p class="wipe-calendar-promo__info-text">
                <?= Yii::t('common', 'Чтобы забрать скин, вам нужно авторизоваться на сайте и поиграть на сервере!') ?>
            </p>
            <p class="wipe-calendar-promo__info-text wipe-calendar-promo__info-text--highlight">
                <?= Yii::t('common', 'Скин "{name}" будет доступен вам после игры на нашем сервере от 12 часов', ['name' => Html::encode($randomSkin['name'])]) ?>
            </p>
            
            <div class="wipe-calendar-promo__actions">
                <div class="wipe-calendar-promo__buttons">
                    <button type="button" class="button-secondary" id="wipe-calendar-promo-close-btn-2">
                        <span class="button__text"><?= Yii::t('common', 'Закрыть') ?></span>
                    </button>
                    <a href="/servers" class="button-primary" id="wipe-calendar-promo-servers-btn">
                        <span class="button__text"><?= Yii::t('common', 'Перейти к серверам') ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wipe-calendar-promo {
    padding: 24px;
    text-align: center;
}

.wipe-calendar-promo__screen {
    min-height: 200px;
}

.wipe-calendar-promo__content {
    display: grid;
    gap: 24px;
}

.wipe-calendar-promo__header {
    display: grid;
    gap: 8px;
}

.wipe-calendar-promo__title {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: var(--text-main);
}

.wipe-calendar-promo__subtitle {
    margin: 0;
    font-size: 16px;
    color: var(--text-secondary);
}

.wipe-calendar-promo__skin {
    display: grid;
    gap: 12px;
    justify-items: center;
}

.wipe-calendar-promo__skin-image {
    width: 200px;
    height: 200px;
    object-fit: contain;
    border-radius: 8px;
}

.wipe-calendar-promo__skin-name {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-main);
}

.wipe-calendar-promo__message {
    display: grid;
    gap: 16px;
    padding: 16px;
    background-color: var(--background-teritiary);
    border-radius: 8px;
}

.wipe-calendar-promo__info-text {
    margin: 0;
    font-size: 14px;
    color: var(--text-secondary);
}

.wipe-calendar-promo__info-text--highlight {
    font-weight: 600;
    color: var(--text-main);
}

.wipe-calendar-promo__servers {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
}

.wipe-calendar-promo__server-link {
    display: inline-block;
    padding: 8px 16px;
    background-color: var(--background-secondary);
    border: 1px solid var(--background-teritiary);
    border-radius: 6px;
    color: var(--text-main);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.wipe-calendar-promo__server-link:hover {
    background-color: var(--background-teritiary);
    color: var(--text-main);
}

.wipe-calendar-promo__actions {
    margin-top: 8px;
}

.wipe-calendar-promo__buttons {
    display: flex;
    gap: 12px;
    justify-content: space-between;
}

.wipe-calendar-promo__buttons .button-secondary,
.wipe-calendar-promo__buttons .button-primary {
    flex: 1;
}
</style>

<script>
(function() {
    const getBtn = document.getElementById('wipe-calendar-promo-get-btn');
    const closeBtn1 = document.getElementById('wipe-calendar-promo-close-btn-1');
    const closeBtn2 = document.getElementById('wipe-calendar-promo-close-btn-2');
    const screen1 = document.getElementById('wipe-calendar-promo-screen-1');
    const screen2 = document.getElementById('wipe-calendar-promo-screen-2');
    
    // Функция для закрытия модального окна
    function closeModal() {
        const modalEl = document.getElementById('modal-dialog');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        }
    }
    
    // Кнопка "Забрать скин" - переключает на второй экран
    if (getBtn && screen1 && screen2) {
        getBtn.addEventListener('click', function() {
            // Скрываем первый экран
            screen1.style.display = 'none';
            // Показываем второй экран
            screen2.style.display = 'block';
        });
    }
    
    // Кнопки "Закрыть" - закрывают модальное окно
    if (closeBtn1) {
        closeBtn1.addEventListener('click', closeModal);
    }
    
    if (closeBtn2) {
        closeBtn2.addEventListener('click', closeModal);
    }
})();
</script>

