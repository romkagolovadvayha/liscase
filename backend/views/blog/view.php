<?php

use yii\helpers\Html;
use common\models\blog\Blog;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

$this->title = $model->name;
$this->params['contentClass'] = 'content-no-padding';

$rowClass = 'flex flex-wrap gap-2 py-3 border-b border-[hsl(0_0%_15.3%_/_1)] last:border-b-0';
$labelClass = 'text-xs text-gray-400 uppercase tracking-wide w-full md:w-32 flex-shrink-0';
$valueClass = 'text-white flex-1 min-w-0';
?>
<div class="blog-view-page w-full p-4 lg:p-6">
    <div class="max-w-4xl">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h1 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Информация о посте') ?></h1>
            </div>
            <div class="p-4">
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">ID</div>
                    <div class="<?= $valueClass ?>"><?= (int)$model->id ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('name') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->name) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('content') ?></div>
                    <div class="<?= $valueClass ?> prose prose-invert max-w-none text-gray-300"><?= $model->content ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('description') ?></div>
                    <div class="<?= $valueClass ?>"><?= nl2br(Html::encode($model->description)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('keywords') ?></div>
                    <div class="<?= $valueClass ?>"><?= nl2br(Html::encode($model->keywords)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('blog_category_id') ?></div>
                    <div class="<?= $valueClass ?>">
                        <?php if ($model->blogCategory): ?>
                            <?= Html::a(Html::encode($model->blogCategory->name), ['/blog-category/view', 'id' => $model->blogCategory->id], ['class' => 'text-blue-400 hover:underline']) ?>
                        <?php else: ?>
                            <span class="text-gray-500">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= Yii::t('common', 'Ссылка на пост') ?></div>
                    <div class="<?= $valueClass ?>">
                        <?php $link = (Yii::$app->params['baseUrl'] ?? '') . $model->getUrl(); ?>
                        <?= Html::a(Html::encode($link), $link, ['target' => '_blank', 'class' => 'text-blue-400 hover:underline break-all']) ?>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('status') ?></div>
                    <div class="<?= $valueClass ?>">
                        <?php
                        $status = ArrayHelper::getValue(Blog::getStatusList(), $model->status, '');
                        $badgeClass = $model->status == Blog::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        ?>
                        <span class="ds-badge <?= $badgeClass ?>"><?= Html::encode($status) ?></span>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('created_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->created_at) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
