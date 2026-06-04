<?php

use common\models\tournament\Tournament;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Tournament $model */
/** @var string|null $publicUrl */

$this->title = $model->title;
$this->params['contentClass'] = 'content-no-padding';

$formatDt = static function (?string $dt): string {
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d.m.Y H:i', $ts) : Html::encode($dt);
};

$tags = $model->getTagsArray();
$registered = $model->getRegisteredClansCount();

$this->params['headerActions'] = array_values(array_filter([
    [
        'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
        'url' => ['index'],
        'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
    $publicUrl ? [
        'label' => '<i class="fas fa-external-link-alt"></i> ' . Yii::t('common', 'На сайте'),
        'url' => $publicUrl,
        'class' => 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ] : null,
    [
        'label' => '<i class="fas fa-pen"></i> ' . Yii::t('common', 'Редактировать'),
        'url' => ['update', 'id' => $model->id],
        'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
    ],
]));
?>

<div class="p-4 lg:p-6 space-y-4 text-white max-w-6xl tournament-view-page">
    <?php if ($model->getCoverUrl()): ?>
        <img src="<?= Html::encode($model->getCoverUrl()) ?>" alt="" class="max-h-36 rounded-lg w-full object-cover border border-[hsl(0_0%_15.3%_/_1)]" />
    <?php endif; ?>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-3 space-y-1.5">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1"><?= Yii::t('common', 'Основное') ?></h2>
            <p><span class="text-gray-400">ID</span> <?= (int)$model->id ?></p>
            <p><span class="text-gray-400">Slug</span> <code class="text-xs"><?= Html::encode($model->slug) ?></code></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Статус') ?></span> <?= Html::encode($model->getStatusLabel()) ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Фаза') ?></span> <?= Html::encode($model->getPhaseLabel()) ?></p>
            <?php if ($tags !== []): ?>
                <p class="flex flex-wrap gap-1 pt-1">
                    <?php foreach ($tags as $tag): ?>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-[hsl(0_0%_22%)] text-gray-300"><?= Html::encode($tag) ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-3 space-y-1.5">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1"><?= Yii::t('common', 'Сроки и лимиты') ?></h2>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Начало') ?></span> <?= $formatDt($model->starts_at) ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Окончание') ?></span> <?= $formatDt($model->ends_at) ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Регистрация до') ?></span> <?= $formatDt($model->registration_ends_at) ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Кланы') ?></span>
                <?= $registered ?><?= $model->max_clans ? ' / ' . (int)$model->max_clans : '' ?></p>
            <?php if ($model->max_participants_per_clan): ?>
                <p><span class="text-gray-400"><?= Yii::t('common', 'Состав') ?></span> ≤ <?= (int)$model->max_participants_per_clan ?></p>
            <?php endif; ?>
        </div>

        <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-3 space-y-1.5">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1"><?= Yii::t('common', 'Отображение') ?></h2>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Сервер') ?></span>
                <?= $model->server ? Html::encode($model->server->name) : '—' ?></p>
            <?php if ($model->format_label): ?>
                <p><span class="text-gray-400"><?= Yii::t('common', 'Формат') ?></span> <?= Html::encode($model->format_label) ?></p>
            <?php endif; ?>
            <?php if ($model->prize_pool_label): ?>
                <p><span class="text-gray-400"><?= Yii::t('common', 'Приз') ?></span> <?= Html::encode($model->prize_pool_label) ?></p>
            <?php endif; ?>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Сортировка') ?></span> <?= (int)$model->sort ?></p>
            <p><span class="text-gray-400"><?= Yii::t('common', 'Регистрация') ?></span>
                <?= $model->isRegistrationOpen() ? Yii::t('common', 'Открыта') : Yii::t('common', 'Закрыта') ?></p>
        </div>
    </section>

    <?php if ($model->description): ?>
        <section class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-3">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2"><?= Yii::t('common', 'Описание') ?></h2>
            <p class="text-sm text-gray-200 whitespace-pre-wrap"><?= Html::encode($model->description) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($model->rewards): ?>
        <section class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-3">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3"><?= Yii::t('common', 'Награды') ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <?php foreach ($model->rewards as $reward): ?>
                    <div class="flex items-center gap-2 p-2 rounded bg-[hsl(0_0%_14%)] border border-[hsl(0_0%_15.3%_/_1)]">
                        <?php if ($reward->getImageUrl()): ?>
                            <img src="<?= Html::encode($reward->getImageUrl()) ?>" alt="" class="w-12 h-12 object-contain flex-shrink-0" />
                        <?php endif; ?>
                        <div class="min-w-0 text-xs">
                            <p class="text-gray-400"><?= (int)$reward->place ?> <?= Yii::t('common', 'место') ?></p>
                            <p class="font-medium truncate"><?= Html::encode($reward->title) ?></p>
                            <?php if ($reward->subtitle): ?>
                                <p class="text-gray-500 truncate"><?= Html::encode($reward->subtitle) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($model->rules_text): ?>
        <section class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] p-3">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2"><?= Yii::t('common', 'Правила') ?></h2>
            <pre class="text-sm text-gray-300 whitespace-pre-wrap font-sans m-0"><?= Html::encode($model->rules_text) ?></pre>
        </section>
    <?php endif; ?>

    <?php if ($model->status === Tournament::STATUS_DRAFT): ?>
        <div class="pt-1">
            <?= Html::beginForm(['delete', 'id' => $model->id], 'post') ?>
            <?= Html::submitButton(Yii::t('common', 'Удалить черновик'), [
                'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
                'data-confirm' => Yii::t('common', 'Удалить турнир?'),
            ]) ?>
            <?= Html::endForm() ?>
        </div>
    <?php endif; ?>
</div>
