<?php $this->beginContent('@frontend/views/layouts/main.php'); ?>

    <div class="white-box">
        <?= \common\components\widgets\Nav::widget([
            'items' => (new \frontend\components\menu\PromotionNavMenu())->getItems(),
        ]); ?>

        <div class="tab-content">
            <?= \common\components\widgets\Alert::widget() ?>

            <?= $content; ?>
        </div>
    </div>

<?php $this->endContent(); ?>