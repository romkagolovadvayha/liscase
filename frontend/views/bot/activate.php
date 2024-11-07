<?php

use yii\bootstrap5\Html;

/** @var \common\models\user\UserConfirmCode $userConfirmModel */

$this->title = Yii::t('common', 'Код активации');

?>
<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="page_content">
                    <div class="page_content_header">
                        <h1><?= Yii::t('common', 'Код активации персонального Telegram-бота'); ?></h1>
                    </div>
                    <div class="page_content_body">

                        <div class="referal_link btn-clipboard"
                             data-bs-toggle="tooltip"
                             data-bs-placement="bottom"
                             data-bs-title="<?=Yii::t('common', 'Скопировать код')?>"
                             data-clipboard-text="<?=$userConfirmModel->code?>"
                             data-message="<?=Yii::t('common', 'Код скопирован в буфер обмена!')?>">
                            <div class="referal_link_title">
                                <span><?=Yii::t('common', "Код активации персонального Telegram-бота")?></span> <i class="fas fa-copy"></i>
                            </div>
                            <div class="referal_link_link">
                                        <span>
                                            <?=$userConfirmModel->code?>
                                        </span>
                            </div>
                        </div>

                        <?php
                        $botLink = Html::a('@' . Yii::$app->params['tgPersonalBot'], 'https://t.me/' . Yii::$app->params['tgPersonalBot'], ['target' => '_blank']);
                        ?>

                        <p class="mt-2">
                            <?= Yii::t('common', 'Скопируйте и вставьте это код в {botLink}', [
                                'botLink' => $botLink
                            ])?>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>