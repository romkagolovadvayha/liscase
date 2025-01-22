<?php

use common\components\helpers\Role;
use yii\helpers\Html;
use yii\widgets\DetailView;
use frontend\forms\support\SupportForm;
use common\models\support\Support;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */
/** @var \common\models\user\User $user */
/** @var \frontend\models\support\SupportSearch[] $tickets */

\frontend\assets\SupportAsset::register($this);
?>
<div class="support_messages_wrap_wrap">
    <div class="support_messages_header">
        <div class="support_messages_header_name"><?=Yii::t('common', 'Поддержка')?></div>
        <div class="support_messages_header_close"><i class="fa-solid fa-xmark"></i></div>
    </div>
    <div class="support_messages_main">
        <div class="support_messages_side">
            <ul class="tickets">
                <?php foreach ($tickets as $ticket): ?>
                    <li>
                        <a href="<?=$ticket->getUrl()?>" class="tickets_item<?=($ticket->id === $model->id) ? ' active' : ''?>">
                            <div class="tickets_item_avatar">
                                <img src="<?=$ticket->user->getAvatar()?>" width="32px"/>
                            </div>
                            <div class="tickets_item_body">
                                <div class="tickets_item_body_name"><?=$ticket->user->username?></div>
                                <div class="tickets_item_body_footer">
                                    <div class="tickets_item_body_footer_status">
                                        <?=ArrayHelper::getValue(Support::getStatusList(), $ticket->status)?>
                                    </div>
                                    <div class="tickets_item_body_footer_date">
                                        <span class="server_timer" data-time="<?=strtotime($ticket->updated_at)?>"><?=$ticket->updated_at?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="support_messages_body">
            <div class="support_messages_wrap">
                <div class="support_messages" id="chat" style="width: 100%;">
                    <?php foreach ($model->supportMessages as $item): ?>
                        <?=$this->render('_message', [
                            'model' => $item
                        ]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="support_messages_form">
                <div id="supportChatWrited" class="support_messages_form_writed"></div>
                <label class="support_messages_form_file">
                    <input type="file" id="supportMessageFile" class="support_messages_form_file_input" />
                    <i class="fa-solid fa-paperclip"></i>
                </label>
                <input id="supportMessage" placeholder="Напишите сообщение..." type="text" class="support_messages_form_input" />
            </div>
        </div>
    </div>
</div>
<script>
    var chatId = <?=$model->getNumber()?>
</script>