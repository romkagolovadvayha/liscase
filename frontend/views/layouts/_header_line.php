<?php

use common\models\blog\BlogCategory;
use yii\widgets\ActiveForm;
use common\models\promocode\Promocode;

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

?>
<div class="container">
    <div class="header_search_line">
        <div class="navigation_bar">
            <?php
            $items = [];
            /** @var BlogCategory[] $categories */
            $categories = BlogCategory::find()->andWhere([
                'blog_category_id' => null,
            ])->all();
            foreach ($categories as $category) {
                $items[] = [
                    'label'   => Yii::t('common', $category->name),
                    'url'     => $category->getUrl()
                ];
            }
            ?>
            <?=\yii\bootstrap5\Nav::widget([
                'items' => [
                    [
                        'label'   => Yii::t('common', "Категории"),
                        'items' => $items
                    ],
                ],
                'options' => ['class' =>'navigation_bar_menu'],
            ]);
            ?>
        </div>
        <div class="search">
            <?php $form = ActiveForm::begin(); ?>
            <?= $form->field($promocodeForm, 'code', [
                'template' => "{label}\n<div class=\"input-group\">{input}\n<span class=\"input-group-btn\"><button type=\"submit\" class=\"btn\">".Yii::t('common', 'Поиск')."</button>\n{hint}\n{error}</span></div>"
            ])->label(false)->textInput(['placeholder' => Yii::t('common', 'Найти...')]); ?>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>