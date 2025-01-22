<?php

use common\components\helpers\Role;
use common\models\statistics\Reports;

/** @var yii\web\View $this */
/** @var \common\models\support\SupportMessage $model */
/** @var \common\models\user\User $user */

?>
<div class="support_messages_item_message_text">
    <?=Yii::t('common', 'Если вы хотите пожаловаться на игрока, нажмите в игре кнопку {PARAM_COMMAND_F7}. Мы видим все ваши жалобы в игре, тикет в поддержку создавать не нужно. Если у вас есть доказательства и откаты вы можете приложить их по кнопке ниже {PARAM_PAPERCLIP}.', [
        'PARAM_COMMAND_F7' => '<span class="support_messages_item_message_success">F7</span>',
        'PARAM_PAPERCLIP' => '<span class="support_messages_item_message_success"><i class="fa-solid fa-paperclip"></i></span>',
    ])?>
</div>