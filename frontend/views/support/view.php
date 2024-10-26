<?php

use common\components\helpers\Role;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */
/** @var \common\models\user\User $user */

$this->title = Yii::t('common', 'Тикет')  . " ID" . $model->getNumber();
\yii\web\YiiAsset::register($this);
\frontend\assets\SupportAsset::register($this);
?>

<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?php echo $this->render('@frontend/views/widgets/_alert'); ?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child support" style="width: 100%;">
                <div class="support_messages" id="chat" style="width: 100%;">
                    <?php foreach ($model->supportMessages as $item): ?>
                        <?php
                            $usernameClass = '';
                            if (Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                                $usernameClass = 'moder';
                            }
                            if (Yii::$app->user->can(Role::ROLE_ADMIN)) {
                                $usernameClass = 'admin';
                            }
                        ?>
                        <div class="support_messages_item">
                            <div class="support_messages_item_profile">
                                <div class="support_messages_item_profile_avatar"><img src="<?=$item->user->getAvatar()?>"></div>
                            </div>
                            <div class="support_messages_item_message">
                                <div class="support_messages_item_message_username <?=$usernameClass?>"><?=$item->user->username?></div>
                                <div class="support_messages_item_message_content"><?=$item->message?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="supportChatWrited" class="support_chat_writed"></div>
                <input id="message" type="text"><button id="btnSend">Send</button>
            </div>
        </main>
    </div>
</div>
<script>
    var chatId = <?=$model->getNumber()?>
</script>