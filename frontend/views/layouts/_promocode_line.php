<?php
use yii\widgets\ActiveForm;
use common\models\promocode\Promocode;

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

$url = Yii::$app->request->url;
function _checkActive($urlStr)
{
    return (bool)strstr(Yii::$app->request->url, $urlStr);
}
$promocode = Promocode::getActivePromocode();
?>
<div class="container">
    <div class="header_promocode_line">
        <?php if (!empty($promocode)): ?>
            <div class="promocode">
                <div>
                <?php if ($promocode->type === Promocode::TYPE_PAYMENT_DISCOUNT): ?>
                    <?=Yii::t('common', 'Действует скидка')?> <?=$promocode->getPercentCeil()?>% <?=Yii::t('common', 'на все товары')?>
                <?php elseif ($promocode->type === Promocode::TYPE_PAYMENT_PAYMENT): ?>
                    <?=Yii::t('common', 'Действует скидка')?> <?=$promocode->getPercentCeil()?>% <?=Yii::t('common', 'на пополнение баланса')?>
                <?php endif; ?>
                </div>
                <div><a href="/promocode/clear"><?=Yii::t('common', 'Отменить')?></a></div>
            </div>
        <?php else: ?>
            <div class="promocode">
                <?php $form = ActiveForm::begin(); ?>
                <?= $form->field($promocodeForm, 'code', [
                        'template' => "{label}\n<div class=\"input-group\">{input}\n<span class=\"input-group-btn\"><button type=\"submit\" class=\"btn\">".Yii::t('common', 'Применить')."</button>\n{hint}\n{error}</span></div>"
                    ])->label(false)->textInput(['placeholder' => Yii::t('common', 'Введите промокод')]); ?>
                <?php ActiveForm::end(); ?>
            </div>
        <?php endif; ?>
        <div class="navigation_bar">
            <?=\yii\bootstrap5\Nav::widget([
                'items' => [
                    [
                        'label'   => Yii::t('common', "Кейсы"),
                        'url'     => '/',
                        'active' => _checkActive('/'),
                    ],
                    [
                        'label'   => Yii::t('common', "Сражения"),
                        'url'     => '/battle/index',
                        'active' => _checkActive('/battle/index'),
                    ],
                    [
                        'label'   => Yii::t('common', "Маркет"),
                        'url'     => '/market/index',
                        'active' => _checkActive('/market/index'),
                    ],
                ],
                'options' => ['class' =>'navigation_bar_menu'],
            ]);
            ?>
        </div>
    </div>
</div>