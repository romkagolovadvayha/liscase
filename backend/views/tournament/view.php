<?php

use common\models\tournament\Tournament;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Tournament $model */
/** @var string|null $publicUrl */

$this->title = $model->title;
$this->params['contentClass'] = 'content-no-padding';
$this->params['headerActions'] = [
    [
        'label' => '<i class="fas fa-pen"></i> ' . Yii::t('common', 'Редактировать'),
        'url' => ['update', 'id' => $model->id],
        'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
    [
        'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
        'url' => ['index'],
        'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
];
?>

<div class="p-4 lg:p-6 max-w-4xl text-white space-y-4">
    <?php if ($publicUrl): ?>
        <p>
            <?= Yii::t('common', 'Публичная ссылка') ?>:
            <?= Html::a(Html::encode($publicUrl), $publicUrl, ['class' => 'text-blue-400 hover:underline', 'target' => '_blank']) ?>
        </p>
    <?php endif; ?>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
        <dt class="text-gray-400">ID</dt><dd><?= (int)$model->id ?></dd>
        <dt class="text-gray-400">Slug</dt><dd><?= Html::encode($model->slug) ?></dd>
        <dt class="text-gray-400"><?= Yii::t('common', 'Сервер') ?></dt>
        <dd><?= Html::encode($model->server ? $model->server->name : '—') ?></dd>
        <dt class="text-gray-400"><?= Yii::t('common', 'Статус') ?></dt><dd><?= Html::encode($model->status) ?></dd>
        <dt class="text-gray-400"><?= Yii::t('common', 'Фаза') ?></dt><dd><?= Html::encode($model->getPublicPhase()) ?></dd>
        <dt class="text-gray-400"><?= Yii::t('common', 'Начало') ?></dt><dd><?= Html::encode($model->starts_at) ?></dd>
        <dt class="text-gray-400"><?= Yii::t('common', 'Окончание') ?></dt><dd><?= Html::encode($model->ends_at) ?></dd>
        <dt class="text-gray-400"><?= Yii::t('common', 'Кланы') ?></dt>
        <dd><?= (int)$model->getRegisteredClansCount() ?><?= $model->max_clans ? ' / ' . (int)$model->max_clans : '' ?></dd>
    </dl>

    <?php if ($model->getCoverUrl()): ?>
        <img src="<?= Html::encode($model->getCoverUrl()) ?>" alt="" class="max-h-48 rounded" />
    <?php endif; ?>

    <?php if ($model->status === Tournament::STATUS_DRAFT): ?>
        <?= Html::beginForm(['delete', 'id' => $model->id], 'post', ['class' => 'pt-4']) ?>
        <?= Html::submitButton(Yii::t('common', 'Удалить черновик'), [
            'class' => 'ds-btn ds-btn--secondary',
            'data-confirm' => Yii::t('common', 'Удалить турнир?'),
        ]) ?>
        <?= Html::endForm() ?>
    <?php endif; ?>
</div>
