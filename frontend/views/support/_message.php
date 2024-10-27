<?php

use common\components\helpers\Role;

/** @var yii\web\View $this */
/** @var \common\models\support\SupportMessage $model */

$usernameClass = '';
if ($model->user->canRoles([Role::ROLE_MODERATOR])) {
    $usernameClass = 'moder';
}
if ($model->user->canRoles([Role::ROLE_ADMIN])) {
    $usernameClass = 'admin';
}
?>
<div class="support_messages_item">
    <div class="support_messages_item_profile">
        <div class="support_messages_item_profile_avatar"><img src="<?=$model->user->getAvatar()?>"></div>
    </div>
    <div class="support_messages_item_message">
        <div class="support_messages_item_message_username <?=$usernameClass?>"><?=$model->user->username?></div>
        <?php if (!empty($model->message)): ?>
            <div class="support_messages_item_message_content"><?=$model->message?></div>
        <?php endif; ?>
        <?php if (!empty($model->supportFiles)): ?>
            <div class="support_messages_item_message_files">
                <?php foreach ($model->supportFiles as $file): ?>
                    <?=$this->render('_file', [
                        'model' => $file
                    ]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
