<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use common\models\support\SupportSticker;

/** @var yii\web\View $this */
/** @var common\models\support\SupportSticker $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Стикеры поддержки'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$rowClass = 'flex flex-wrap gap-2 py-3 border-b border-[hsl(0_0%_15.3%_/_1)] last:border-b-0';
$labelClass = 'text-xs text-gray-400 uppercase tracking-wide w-full md:w-32 flex-shrink-0';
$valueClass = 'text-white flex-1 min-w-0';
?>
<div class="support-sticker-view w-full p-4 lg:p-6">
    <div class="max-w-4xl">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h1 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Html::encode($this->title) ?></h1>
            </div>
            <div class="p-4">
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">ID</div>
                    <div class="<?= $valueClass ?>"><?= (int)$model->id ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('code') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->code) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('name') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->name) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('type') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode(ArrayHelper::getValue(SupportSticker::getTypeList(), $model->type)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('file') ?></div>
                    <div class="<?= $valueClass ?>">
                        <?php if ($model->type === SupportSticker::TYPE_IMAGE): ?>
                            <?= Html::img($model->getPublicUrl(), ['class' => 'max-w-[300px] max-h-[300px] rounded']) ?>
                        <?php else: ?>
                            <video src="<?= Html::encode($model->getPublicUrl()) ?>" class="max-w-[300px] max-h-[300px] rounded" controls></video>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('status') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode(ArrayHelper::getValue(SupportSticker::getStatusList(), $model->status)) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('width') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->width) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('height') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->height) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('sort') ?></div>
                    <div class="<?= $valueClass ?>"><?= (int)$model->sort ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('created_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('updated_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Yii::$app->formatter->asDatetime($model->updated_at) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">HTML-тег для использования</div>
                    <div class="<?= $valueClass ?>">
                        <pre class="bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded px-3 py-2 text-sm text-gray-300 overflow-x-auto"><?= Html::encode($model->getHtmlTag()) ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
