<?php

use yii\web\View;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Партнерская программа") . " - {$user->userProfile->name}";
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_inventory_menu'); ?>
        <div class="tab-content mt-2">
            <label class="form-label" for="partner_link">
                <?=Yii::t('common', "Ваша персональная партнерская ссылка")?>
            </label>
            <div class="mb-3 required">
                <input type="text" id="partner_link" class="form-control" value="<?=$user->getPartnerLink()?>" readonly>
            </div>
        </div>
    </div>
</main>
