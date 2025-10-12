<?php

use common\components\helpers\Role;
use common\models\support\Support;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */
/** @var \frontend\models\support\SupportSearch[] $tickets */
/** @var \common\models\user\User $user */
/** @var array $unreadMessages */

if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
    $openTicket = false;
    foreach ($tickets as $ticket) {
        if ($ticket->status === Support::STATUS_OPEN) {
            $openTicket = true;
            break;
        }
    }
    if (!$openTicket) {
        $nModel = new Support();
        $nModel->user_id = $user->id;
        $nModel->status = Support::STATUS_OPEN;
        $nModel->created_at = date('Y-m-d H:i:s');
        $nModel->updated_at = date('Y-m-d H:i:s');
        $tickets = ArrayHelper::merge([$nModel], $tickets);
    }
}
?>
<ul class="tickets">
    <?php foreach ($tickets as $ticket): ?>
        <?php $unread = !(empty($unreadMessages[$ticket->id])) ? $unreadMessages[$ticket->id]['cnt'] : 0; ?>
        <li>
            <a href="<?=$ticket->getUrl()?>" class="tickets_item<?=($ticket->status === Support::STATUS_CLOSED) ? ' closed' : ''?><?=($ticket->id === $model->id) ? ' active' : ''?>" data-pjax="1">
                <div class="tickets_item_avatar">
                    <img src="<?=$ticket->user->getAvatar()?>" width="32px"/>
                </div>
                <div class="tickets_item_body">
                    <div class="tickets_item_body_name">
                        <?php if ($ticket->status === Support::STATUS_OPEN): ?>
                            <?php if ($user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])): ?>
                                <?=$ticket->user->username?>
                            <?php else: ?>
                                <?php if (!empty($ticket->id)): ?>
                                    <?=ArrayHelper::getValue(Support::getStatusList(), $ticket->status)?>
                                <?php else: ?>
                                    <?=Yii::t('common', 'Новый тикет')?>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?=ArrayHelper::getValue(Support::getStatusList(), $ticket->status)?>
                        <?php endif; ?>
                    </div>
                    <div class="tickets_item_body_footer">
                        <?php if (!empty($ticket->id)): ?>
                            <div class="tickets_item_body_footer_date">
                                <span class="ticket_timer" data-time="<?=strtotime($ticket->updated_at)?>"><?=$ticket->updated_at?></span>
                            </div>
                        <?php else: ?>
                            <div class="tickets_item_body_footer_date">
                                <?=Yii::t('common', 'Напишите сообщение')?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($unread > 0): ?>
                <div class="tickets_item_notification">
                    <?=$unread?>
                </div>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>