<?php

use common\components\helpers\Role;
use common\models\statistics\Reports;

/** @var yii\web\View $this */
/** @var \common\models\support\SupportMessage $model */
/** @var \common\models\user\User $user */

$server = null;
if (!empty($model->support)) {
    if (!empty($model->support->server)) {
        $server = $model->support->server;
    }
    $ticketUser = $model->support->user;
} else {
    $ticketUser = $user;
    $server = $user->server;
}

$isUser = empty($model->support_id) || $ticketUser->id == $user->id;

/** @var Reports[] $reports */
$reports = Reports::find()
    ->andWhere(['steam_id' => $ticketUser->steam_id])
    ->orderBy(['id' => SORT_DESC])
    ->limit(3)
    ->all();

?>
<div class="support_messages_item_message_text">
    <?php if ($isUser): ?>
        <?=Yii::t('common', 'Сервер на котором вы играете')?>:
    <?php else: ?>
        <?=Yii::t('common', 'Сервер игрока')?>:
    <?php endif; ?> <?=(!empty($server)) ? Yii::t('database', $server->name) : Yii::t('common', 'неизвестно')?>
</div>
<div class="support_messages_item_message_text">
    <?php if ($isUser): ?>
        <?=Yii::t('common', 'Ваши последние жалобы на игроков')?>:
    <?php else: ?>
        <?=Yii::t('common', 'Последние репорты игрока')?>:
    <?php endif; ?>
    <?php if (empty($reports)): ?>
        <?php if ($isUser): ?>
            <?=Yii::t('common', 'Вы не отправили ни одной жалобы на сервере! Чтобы отправить жалобу нажмите {PARAM_COMMAND_F7} в игре.', [
                'PARAM_COMMAND_F7' => '<span class="support_messages_item_message_success">F7</span>',
            ])?>
        <?php else: ?>
            <?=Yii::t('common', 'Игрок не отправил ни одного репорта!')?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php if (!empty($reports)): ?>
    <div class="support_messages_item_message_reports">
        <?php foreach ($reports as $item): ?>
        <a href="<?=$item->user->getLink('stats')?>"
           data-bs-toggle="tooltip"
           data-bs-placement="bottom"
           data-bs-title="<?=Yii::t('common', 'Причина: ')?> <?=$item->reason?>"
           target="_blank"
           class="support_messages_item_message_reports_item">
            <div class="support_messages_item_message_reports_item_avatar">
                <img src="<?=$item->user->getAvatar()?>" width="50px">
            </div>
            <div class="support_messages_item_message_reports_item_content">
                <div class="support_messages_item_message_reports_item_content_name"><?=$item->user->username?></div>
                <div class="support_messages_item_message_reports_item_content_steam_id"><?=$item->user->steam_id?></div>
                <div class="support_messages_item_message_reports_item_content_date server_timer" data-time="<?=strtotime($item->user->created_at)?>"><?=$item->user->created_at?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
