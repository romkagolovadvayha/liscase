<?php

use common\components\helpers\Role;
use yii\helpers\Html;
use yii\widgets\DetailView;
use frontend\forms\support\SupportForm;
use common\models\support\Support;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */
/** @var array $unreadMessages */
/** @var common\models\support\SupportMessage $uiModel */
/** @var \common\models\user\User $user */
/** @var \frontend\models\support\SupportSearch[] $tickets */

\frontend\assets\SupportAsset::register($this);
?>
<div class="support_messages_wrap_wrap">
    <div class="support_messages_header">
        <div class="support_messages_header_name"><?=Yii::t('common', 'Поддержка')?></div>
        <div class="support_messages_header_actions">
            <?php if ($user->canRoles([Role::ROLE_MODERATOR, Role::ROLE_ADMIN, Role::ROLE_SUPPORT])): ?>
                <div class="dropdown">
                    <a class="button button-secondary button-size__s h-36" type="button" id="chat_tools" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="button__text"><i class="fas fa-cog"></i> <?=Yii::t('common', 'Управление')?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="chat_tools">
                        <?php if (strtotime($model->user->blocked_support_at) > time()): ?>
                            <li><a class="dropdown-item" href="/support/mute?support_id=<?=$model->getNumber()?>&user_id=<?=$model->user_id?>&blocked=0"><?=Yii::t('common', 'Снять мут с игрока')?></a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="/support/mute?support_id=<?=$model->getNumber()?>&user_id=<?=$model->user_id?>"><?=Yii::t('common', 'Выдать мут на 30 минут')?></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($model->user->blocked_support): ?>
                            <li><a class="dropdown-item" href="/support/blocked-chat?support_id=<?=$model->getNumber()?>&user_id=<?=$model->user_id?>&blocked=0"><?=Yii::t('common', 'Разблокировать чат')?></a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="/support/blocked-chat?support_id=<?=$model->getNumber()?>&user_id=<?=$model->user_id?>"><?=Yii::t('common', 'Заблокировать чат')?></a></li>
                        <?php endif; ?>
                        <?php if ($user->canRoles([Role::ROLE_ADMIN])): ?>
                            <?php if ($model->user->status === \common\models\user\User::STATUS_BLOCKED): ?>
                                <li><a class="dropdown-item" href="/support/blocked?support_id=<?=$model->getNumber()?>&user_id=<?=$model->user_id?>&blocked=0"><?=Yii::t('common', 'Разблокировать аккаунт')?></a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="/support/blocked?support_id=<?=$model->getNumber()?>&user_id=<?=$model->user_id?>"><?=Yii::t('common', 'Заблокировать аккаунт')?></a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($model->id) && $model->status === Support::STATUS_OPEN): ?>
                <a href="<?=$model->getUrl('close')?>" class="button button-secondary button-size__s h-36">
                    <span class="button__text"><i class="fas fa-times"></i> <?=Yii::t('common', 'Закрыть тикет')?></span>
                </a>
            <?php endif; ?>
            <?php if ($model->status === Support::STATUS_CLOSED && $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])): ?>
                <a href="<?=$model->getUrl('open')?>" class="button button-secondary button-size__s h-36">
                    <span class="button__text"><i class="fas fa-pen"></i> <?=Yii::t('common', 'Открыть тикет')?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="support_messages_main">
        <div class="support_messages_side">
           <div class="tickets_wrap">
               <?=$this->render('_tickets', [
                   'model' => $model,
                   'tickets' => $tickets,
                   'user' => $user,
                   'unreadMessages' => $unreadMessages,
               ]); ?>
           </div>
        </div>
        <div class="support_messages_body">
            <div class="support_messages_wrap">
                <div class="support_messages" id="chat" style="width: 100%;">
                    <?php if (!empty($fModels)): ?>
                        <?php foreach ($fModels as $item): ?>
                            <?=$this->render('_message', [
                                'model' => $item
                            ]); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php foreach ($model->supportMessages as $item): ?>
                        <?=$this->render('_message', [
                            'model' => $item
                        ]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($model->status === Support::STATUS_OPEN || $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])): ?>
                <div class="support_messages_form">
                    <?php if (strtotime($user->blocked_support_at) > time()): ?>
                        <span><?=Yii::t('common', 'Доступ в чат будет разблокирован')?> <span class="ticket_timer" data-time="<?=strtotime($user->blocked_support_at)?>"><?=$user->blocked_support_at?></span></span>
                    <?php else: ?>
                        <?php if (!empty($model->id)): ?>
                            <div id="supportChatWrited" class="support_messages_form_writed"></div>
                        <?php endif; ?>
                        <div class="support_messages_form_progress"></div>
                        <label class="support_messages_form_file">
                            <input type="file" id="supportMessageFile" class="support_messages_form_file_input" accept=".png,.jpg,.gif,.jpeg,.txt,.mp4,.avi,.mov,.webm,.ogg" />
                            <i class="fa-solid fa-paperclip"></i>
                        </label>
                        <button class="sticker-button" type="button" title="<?=Yii::t('common', 'Стикеры')?>">
                            <i class="fa-solid fa-smile"></i>
                        </button>
                        <textarea id="supportMessage" rows="1" placeholder="<?=Yii::t('common', 'Напишите сообщение...')?>" type="text" class="support_messages_form_input"></textarea>
                        <div class="support_messages_form_send" id="supportMessageSend">
                            <i class="fa-solid fa-paper-plane"></i>
                        </div>
                        
                        <!-- Панель стикеров -->
                        <div class="stickers-panel" style="display: none;">
                            <div class="stickers-grid"></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    var chatId = <?=$model->getNumber()?>
    
    // Инициализация стикеров после загрузки DOM и скриптов
    function initStickers() {
        console.log('initStickers called, SupportStickers available:', typeof SupportStickers !== 'undefined');
        console.log('Sticker button exists:', document.querySelector('.sticker-button') !== null);
        console.log('Stickers panel exists:', document.querySelector('.stickers-panel') !== null);
        
        if (typeof SupportStickers !== 'undefined') {
            window.supportStickersInstance = new SupportStickers();
            console.log('SupportStickers initialized successfully');
        } else {
            // Повторяем попытку через небольшую задержку
            console.log('SupportStickers not available, retrying...');
            setTimeout(initStickers, 100);
        }
    }
    
    // Запускаем инициализацию после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStickers);
    } else {
        initStickers();
    }
</script>