<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $path Текущий префикс (каталог) */
/** @var string[] $prefixes Подкаталоги (ключи без завершающего слэша) */
/** @var array{array{key: string, size: int, lastModified: string}} $objects Файлы */
/** @var \common\components\storage\S3Api|null $s3Api */
/** @var string|null $error Сообщение об ошибке */

$this->title = 'Файлы S3';
$this->params['breadcrumbs'][] = ['label' => $this->title, 'url' => ['index']];
if ($path !== '') {
    $this->params['breadcrumbs'][] = $path;
}

$pathParts = $path === '' ? [] : explode('/', $path);
?>

<div class="s3-storage-page w-full flex flex-col min-h-0 flex-1 p-4">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0">Файлы S3</h2>
        <?php if ($s3Api !== null && ($path !== '' || !empty($prefixes) || !empty($objects))): ?>
            <?= Html::beginForm(['set-headers'], 'post', ['class' => 'inline']) ?>
                <?= Html::hiddenInput('prefix', $path) ?>
                <?= Html::submitButton(
                    '<i class="fas fa-cloud-upload-alt mr-1"></i> Установить заголовки кэша (30 дней)',
                    ['class' => 'ds-btn ds-btn--primary text-sm py-1.5 px-3']
                ) ?>
            <?= Html::endForm() ?>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="rounded-lg border border-red-500/50 bg-red-500/10 text-red-300 px-4 py-3 mb-4">
            <?= Html::encode($error) ?>
        </div>
    <?php endif; ?>

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="rounded-lg border border-green-500/50 bg-green-500/10 text-green-300 px-4 py-3 mb-4">
            <?= Html::encode(Yii::$app->session->getFlash('success')) ?>
        </div>
    <?php endif; ?>
    <?php if (Yii::$app->session->hasFlash('error')): ?>
        <div class="rounded-lg border border-red-500/50 bg-red-500/10 text-red-300 px-4 py-3 mb-4">
            <?= Html::encode(Yii::$app->session->getFlash('error')) ?>
        </div>
    <?php endif; ?>

    <!-- Хлебные крошки -->
    <nav class="flex items-center gap-1 text-sm mb-4 flex-wrap rounded-md bg-[hsl(0_0%_18%_/_1)] px-3 py-2 border border-[hsl(0_0%_25%_/_1)]">
        <a href="<?= Html::encode(Url::to(['index'])) ?>" class="text-blue-400 hover:text-blue-300 hover:underline">Корень</a>
        <?php $acc = ''; foreach ($pathParts as $i => $part): ?>
            <?php $acc .= ($acc === '' ? '' : '/') . $part; ?>
            <span class="text-gray-500">/</span>
            <?php if ($i === count($pathParts) - 1): ?>
                <span class="text-gray-300"><?= Html::encode($part) ?></span>
            <?php else: ?>
                <a href="<?= Html::encode(Url::to(['index', 'path' => $acc])) ?>" class="text-blue-400 hover:text-blue-300 hover:underline"><?= Html::encode($part) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="text-gray-400 border-b border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20%_/_1)]">
                <tr>
                    <th class="py-2 px-3">Имя</th>
                    <th class="py-2 px-3 w-28">Размер</th>
                    <?php if ($s3Api): ?>
                        <th class="py-2 px-3 w-40">Ссылка</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prefixes as $prefixKey): ?>
                    <?php
                    $name = $path === '' ? $prefixKey : substr($prefixKey, strlen($path) + 1);
                    $name = explode('/', $name)[0] ?? $name;
                    ?>
                    <tr class="border-b border-[hsl(0_0%_15.3%_/_1)] hover:bg-[hsl(0_0%_22%_/_1)]">
                        <td class="py-2 px-3">
                            <a href="<?= Html::encode(Url::to(['index', 'path' => $prefixKey])) ?>" class="text-blue-400 hover:underline">
                                <i class="fas fa-folder text-yellow-500 mr-2"></i><?= Html::encode($name) ?>
                            </a>
                        </td>
                        <td class="py-2 px-3 text-gray-500">—</td>
                        <?php if ($s3Api): ?>
                            <td class="py-2 px-3">—</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($objects as $obj): ?>
                    <?php
                    $key = $obj['key'];
                    $name = $path === '' ? $key : (substr($key, strlen($path) + 1) ?: basename($key));
                    $size = $obj['size'];
                    $sizeStr = $size === 0 ? '—' : ($size < 1024 ? $size . ' B' : ($size < 1024 * 1024 ? round($size / 1024, 1) . ' KB' : round($size / (1024 * 1024), 1) . ' MB'));
                    $fileUrl = $s3Api ? $s3Api->getPublicUrl($key) : '';
                    ?>
                    <tr class="border-b border-[hsl(0_0%_15.3%_/_1)] hover:bg-[hsl(0_0%_22%_/_1)]">
                        <td class="py-2 px-3 text-gray-300">
                            <i class="fas fa-file text-gray-500 mr-2"></i><?= Html::encode($name) ?>
                        </td>
                        <td class="py-2 px-3 text-gray-500"><?= $sizeStr ?></td>
                        <?php if ($s3Api): ?>
                            <td class="py-2 px-3">
                                <a href="<?= Html::encode($fileUrl) ?>" target="_blank" rel="noopener" class="text-blue-400 hover:underline text-xs">Открыть</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($prefixes) && empty($objects) && !$error): ?>
            <div class="py-8 text-center text-gray-500 text-sm">
                В этой папке пока нет файлов.
            </div>
        <?php endif; ?>
    </div>
</div>
