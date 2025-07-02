<?php
/** @var string $title */
/** @var string $text */
/** @var string|null $btnUrl */
/** @var string|null $btnText */
/** @var User $user */
/** @var $page */
/** @var $addClass */
$customClass = isset($addClass) ? 'img_popup_ui--' . $addClass : '';

use common\models\user\User;
$imgPopup = <<<JS
var popupWrapper1 = document.querySelector('.img_popup_ui');
var popupWin1 = document.querySelector('.img_popup_ui__win');
var closeBtn1 = document.querySelector('.img_popup_ui__win .close_popup_btn');

closeBtn1.addEventListener('click', () => {
    popupWrapper1.classList.remove('active');
});

popupWrapper1.addEventListener('click', (e) => {
    if (!popupWin1.contains(e.target)) {
        popupWrapper1.classList.remove('active');
    }
});

var popupCounterKey = '$page';

JS;

$this->registerJs($imgPopup, \yii\web\View::POS_END);
?>

<div class="img_popup_ui active <?= $customClass ?>">
    <div class="img_popup_ui__win">
        <div class="close_popup_btn"></div>
        <div class="img_popup_ui__content">
            <div class="img_popup_ui__popup_img"></div>
            <h3 class="img_popup_ui__title">
                <?= $title ?>
            </h3>
            <p class="img_popup_ui__txt">
                <?= $text ?>
            </p>
        </div>
        <?php if (!empty($btnText)) : ?>
            <div class="img_popup_ui__btn_row">
                <a href="<?= $btnUrl ?>" class="button main_btn standard_btn standard_button custom_button">
                    <?= $btnText ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
