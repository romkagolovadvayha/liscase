<?php
use common\models\support\Support;
use frontend\forms\support\SupportForm;

/** @var SupportForm $ticket */
$ticket = SupportForm::find()
                 ->andWhere(['user_id' => \Yii::$app->user->id])
                 ->andWhere(['status' => Support::STATUS_OPEN])
                 ->one();

if (empty($ticket)) {
    $ticket = new SupportForm();
    $ticket->saveRecord();
}
?>

<div class="widget_chat" id="widget_chat">
    <div class="widget_chat_body">

    </div>
    <div class="widget_chat_button show-chat-js" data-href="/support/ticket?id=<?=$ticket->getNumber()?>">
        <div class="widget_chat_button_name"><i class="fa-regular fa-envelope"></i> <?=Yii::t('common', 'Поддержка')?></div>
        <div class="widget_chat_button_badge">5</div>
    </div>
</div>