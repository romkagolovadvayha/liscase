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

<div class="container mt-5 mb-5">
    <div class="main_child support" style="width: 100%;">
        <div class="support_messages_wrap_wrap" style="width: 100%;">
            <div class="support_messages_wrap" style="width: 100%;">
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