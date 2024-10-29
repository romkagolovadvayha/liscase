<?php

use common\models\servers\Servers;

$url = Yii::$app->request->url;

?>

<div class="profile_widget_wrap">
    <div class="profile_widget">
        <div class="profile_widget_body">
            <div class="profile_widget_image">
                <img src="<?=Yii::$app->user->identity->getAvatar()?>" alt="<?=Yii::t('common', 'Фото пользователя')?> <?=Yii::$app->user->identity->username?>"/>
            </div>
            <div class="profile_widget_username"><?=Yii::$app->user->identity->username?></div>
            <?= $this->render('@frontend/views/layouts/_inventory_menu'); ?>
        </div>
    </div>
</div>
