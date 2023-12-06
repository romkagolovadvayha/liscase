<?php

/** @var yii\web\View $this */
/** @var \common\models\user\User $user */

?>

<section class="block">
    <header class="block_header">
        <div class="block_header_container">
            <h1 class="block_header_container_title"><?=Yii::t('common', 'Карточка пользователя')?> <?=$user->username?></h1>
        </div>
    </header>
    <div class="block_body">
        <div class="block_body_user">
            <div class="block_body_user_avatar">
                <img src="<?=$user->getAvatar()?>" alt="<?=Yii::t('common', 'Аватар пользователя')?> <?=$user->username?>"/>
            </div>
            <div class="block_body_user_range">
                <?=$this->render('_range', ['range' => $user->getRange()])?>
            </div>
            <ul class="block_body_user_info">
                <li><?=Yii::t('common', 'Ник')?>: <?=$user->username?></li>
                <li><?=Yii::t('common', 'Имя')?>: <?=Yii::t('database', $user->userProfile->name)?></li>
                <li><?=Yii::t('common', 'Фамилия')?>: <?=Yii::t('database', $user->userProfile->surname)?></li>
                <li><?=Yii::t('common', 'Дата рождения')?>: <?=$user->userProfile->birthday?></li>
                <li><?=Yii::t('common', 'Пол')?>: <?=$user->userProfile->gender === 1 ? Yii::t('common', 'Мужской') : Yii::t('common', 'Женский') ?></li>
            </ul>
        </div>
    </div>
</section>