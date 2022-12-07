<?php
use common\models\user\UserDrop;
use yii\web\View;
use common\models\box\Drop;

/** @var View $this */
/** @var UserDrop $userDrop */
/** @var bool $opened */

/** @var Drop $drop **/

?>
<?php foreach ($userDrop->drop as $index => $drop): ?>
    <a href="/market/view?id=<?=$drop->id?>" class="last_drops_item<?=!empty($opened) && $opened ? ' opened' : ''?><?=' level' . $drop->getLevel()?>">
        <div class="last_drops_item_content">
            <div class="last_drops_item_image">
                <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $drop->name) ?>">
            </div>
            <div class="last_drops_item_box_image">
                <img src="<?= $userDrop->box->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $userDrop->box->name) ?>">
            </div>
            <div class="last_drops_item_title"><?=$drop->getShortName()?></div>
        </div>
        <div class="last_drops_item_content_back">
            <div class="last_drops_item_user">
                <div class="last_drops_item_user_avatar">
                    <img src="<?= $userDrop->user->userProfile->avatar ?>" alt="<?= $userDrop->user->userProfile->name ?>">
                </div>
                <div class="last_drops_item_user_name"><?= $userDrop->user->userProfile->name ?></div>
            </div>
        </div>
    </a>
<?php endforeach; ?>