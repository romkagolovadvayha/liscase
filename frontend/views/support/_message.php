<?php

use common\components\helpers\Role;

/** @var yii\web\View $this */
/** @var \common\models\support\SupportMessage $model */

$usernameClass = '';
$user = Yii::$app->user->identity;
if (!empty($model->user) && $model->user->canRoles([Role::ROLE_MODERATOR])) {
    $usernameClass = 'moder';
}
if (!empty($model->user) && $model->user->canRoles([Role::ROLE_ADMIN])) {
    $usernameClass = 'admin';
}
if (empty($model->user)) {
    $usernameClass = 'bot';
}

$avatar = $model->user->getAvatar();
$username = $model->user->getUsername();

?>
<?php if (!empty($model->user_id)): ?>
    <div class="support_messages_item">
        <div class="support_messages_item_profile">
            <div class="support_messages_item_profile_avatar"><img src="<?=$avatar?>"></div>
        </div>
        <div class="support_messages_item_message">
            <?php if ($model->user->id !== $user->id): ?>
                <div class="support_messages_item_message_username <?=$usernameClass?>"><?=$username?></div>
            <?php endif; ?>
            <?php if (!empty($model->message)): ?>
                <div class="support_messages_item_message_content">
                    <div class="support_messages_item_message_text" style="white-space: pre-line;"><?=$model->message?></div>
                </div>
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
        <div class="support_messages_item_date ticket_timer" data-time="<?=strtotime($model->created_at)?>">
            <?=$model->created_at?>
        </div>
    </div>
<?php else: ?>
    <div class="support_messages_item <?=$usernameClass?>">
        <div class="support_messages_item_message">
            <div class="support_messages_item_message_username"><?=Yii::t('common', 'Автоматическое сообщение')?></div>
            <div class="support_messages_item_message_content">
                <?php if ($model->message == '{USER_INFO}'): ?>
                    <?=$this->render('message/_user_info', [
                        'model' => $model,
                        'user' => $user,
                    ]); ?>
                <?php elseif ($model->message == '{ALERT_REPORT}'): ?>
                    <?=$this->render('message/_alert_report', [
                        'model' => $model,
                        'user' => $user,
                    ]); ?>
                <?php else: ?>
                    <div class="support_messages_item_message_text">
                        <?=$model->message?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="support_messages_item_date ticket_timer" data-time="<?=strtotime($model->created_at)?>">
            <?=$model->created_at?>
        </div>
    </div>
<?php endif; ?>
