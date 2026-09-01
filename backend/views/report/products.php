<?php

use yii\helpers\Html;
use common\models\box\Drop;

/** @var array $data */
/** @var \common\models\box\Drop $drop */

$this->title = Yii::t('common', 'Отчет по покупкам');
$this->params['contentClass'] = 'content-no-padding';

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

// Подготовка данных для графиков
$chartLabels = [];
$chartTotalPurchases = [];
$chartTotalRevenue = [];
$totalAllPurchases = 0;
$totalAllRevenue = 0;
$totalUniqueProducts = 0;
$topProducts = [];
$allProductsData = [];

foreach ($data as $date => $_data) {
    $chartLabels[] = $_data['month'];
    $monthPurchases = 0;
    $monthRevenue = 0;

    foreach ($_data['products'] as $productData) {
        $drop = Drop::findOne($productData['drop_id']);
        if ($drop) {
            $count = $productData['count'];
            $revenue = $count * $drop->getRealPrice();
            $monthPurchases += $count;
            $monthRevenue += $revenue;

            if (!isset($allProductsData[$drop->id])) {
                $allProductsData[$drop->id] = [
                    'drop' => $drop,
                    'count' => 0,
                    'revenue' => 0
                ];
            }
            $allProductsData[$drop->id]['count'] += $count;
            $allProductsData[$drop->id]['revenue'] += $revenue;
        }
    }

    $chartTotalPurchases[] = $monthPurchases;
    $chartTotalRevenue[] = $monthRevenue;
    $totalAllPurchases += $monthPurchases;
    $totalAllRevenue += $monthRevenue;
    $totalUniqueProducts += count($_data['products']);
}

usort($allProductsData, function ($a, $b) {
    return $b['count'] - $a['count'];
});
$topProducts = array_slice($allProductsData, 0, 10);

$avgPurchasePrice = $totalAllPurchases > 0 ? $totalAllRevenue / $totalAllPurchases : 0;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>
<div class="products-report-page w-full p-4 lg:p-6">
    <!-- Общая статистика -->
    <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Общая статистика за') ?> <?= count($data) ?> <?= Yii::t('common', 'мес.') ?></h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Всего покупок') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalAllPurchases, 0, '.', ' ') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Общая выручка (руб.)') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalAllRevenue, 0, '.', ' ') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Уникальных предметов') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalUniqueProducts, 0, '.', ' ') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Средняя цена покупки (руб.)') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($avgPurchasePrice, 0, '.', ' ') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Графики -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Динамика покупок по месяцам') ?></h3>
            </div>
            <div class="p-4" style="position: relative; height: 300px;">
                <canvas id="purchasesChart"></canvas>
            </div>
        </div>
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Топ-10 самых покупаемых предметов') ?></h3>
            </div>
            <div class="p-4" style="position: relative; height: 300px;">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Топ продуктов -->
    <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Топ-10 самых популярных предметов') ?></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-auto products-report-table" aria-label="<?= Html::encode(Yii::t('common', 'Топ-10 самых популярных предметов')) ?>">
                <thead>
                    <tr>
                        <th class="<?= $headerCellClass ?>">#</th>
                        <th class="<?= $headerCellClass ?>" style="width: 60px;"><?= Yii::t('common', 'Изображение') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Предмет') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Количество покупок') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Выручка') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Средняя цена') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $index = 1;
                    foreach ($topProducts as $productData): ?>
                        <?php $drop = $productData['drop']; ?>
                        <tr>
                            <td class="<?= $bodyCellClass ?>"><?= $index++ ?></td>
                            <td class="<?= $bodyCellClass ?>">
                                <?php $imgUrl = $drop->image(); ?>
                                <?php if ($imgUrl): ?>
                                    <?= Html::img($imgUrl, [
                                        'width' => 40,
                                        'height' => 40,
                                        'loading' => 'lazy',
                                        'alt' => Html::encode($drop->name ?? ''),
                                        'class' => 'rounded object-cover'
                                    ]) ?>
                                <?php else: ?>
                                    <span class="text-gray-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $bodyCellClass ?>">
                                <?= Html::a(Html::encode($drop->name), ['/drop/update', 'id' => $drop->id], ['class' => 'text-blue-400 hover:underline']) ?>
                            </td>
                            <td class="<?= $bodyCellClass ?>"><strong><?= number_format($productData['count'], 0, '.', ' ') ?></strong></td>
                            <td class="<?= $bodyCellClass ?>"><strong><?= number_format($productData['revenue'], 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></strong></td>
                            <td class="<?= $bodyCellClass ?>"><?= number_format($productData['revenue'] / $productData['count'], 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Детальная информация по месяцам -->
    <?php foreach ($data as $date => $_data): ?>
        <?php
        $monthTotalPurchases = 0;
        $monthTotalRevenue = 0;
        foreach ($_data['products'] as $productData) {
            $drop = Drop::findOne($productData['drop_id']);
            if ($drop) {
                $monthTotalPurchases += $productData['count'];
                $monthTotalRevenue += $productData['count'] * $drop->getRealPrice();
            }
        }
        $monthAvgPrice = $monthTotalPurchases > 0 ? $monthTotalRevenue / $monthTotalPurchases : 0;
        ?>
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Html::encode($_data['month']) ?> <?= date('Y', strtotime($date)) ?></h2>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                        <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Всего покупок') ?></div>
                        <div class="text-white font-semibold"><?= number_format($monthTotalPurchases, 0, '.', ' ') ?></div>
                    </div>
                    <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                        <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Общая выручка') ?></div>
                        <div class="text-white font-semibold"><?= number_format($monthTotalRevenue, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                    </div>
                    <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                        <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Уникальных предметов') ?></div>
                        <div class="text-white font-semibold"><?= count($_data['products']) ?></div>
                    </div>
                    <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                        <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Средняя цена') ?></div>
                        <div class="text-white font-semibold"><?= number_format($monthAvgPrice, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-[hsl(0_0%_15.3%_/_1)]">
                    <table class="w-full text-sm table-auto products-report-table" aria-label="<?= Html::encode(Yii::t('common', 'Покупки предметов за {month}', ['month' => $_data['month'] . ' ' . date('Y', strtotime($date))])) ?>">
                        <thead>
                            <tr>
                                <th class="<?= $headerCellClass ?>">#</th>
                                <th class="<?= $headerCellClass ?>" style="width: 60px;"><?= Yii::t('common', 'Изображение') ?></th>
                                <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Предмет') ?></th>
                                <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Количество покупок') ?></th>
                                <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Цена за единицу') ?></th>
                                <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Общая сумма') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $index = 1;
                            foreach ($_data['products'] as $productData):
                                $drop = Drop::findOne($productData['drop_id']);
                                if (!$drop) continue;
                                $unitPrice = $drop->getRealPrice();
                                $totalPrice = $productData['count'] * $unitPrice;
                            ?>
                                <tr>
                                    <td class="<?= $bodyCellClass ?>"><?= $index++ ?></td>
                                    <td class="<?= $bodyCellClass ?>">
                                        <?php $imgUrl = $drop->image(); ?>
                                        <?php if ($imgUrl): ?>
                                            <?= Html::img($imgUrl, [
                                                'width' => 40,
                                                'height' => 40,
                                                'loading' => 'lazy',
                                                'alt' => Html::encode($drop->name ?? ''),
                                                'class' => 'rounded object-cover'
                                            ]) ?>
                                        <?php else: ?>
                                            <span class="text-gray-500">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $bodyCellClass ?>">
                                        <?= Html::a(Html::encode($drop->name), ['/drop/update', 'id' => $drop->id], ['class' => 'text-blue-400 hover:underline']) ?>
                                    </td>
                                    <td class="<?= $bodyCellClass ?>"><strong><?= number_format($productData['count'], 0, '.', ' ') ?></strong></td>
                                    <td class="<?= $bodyCellClass ?>"><?= number_format($unitPrice, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></td>
                                    <td class="<?= $bodyCellClass ?>"><strong><?= number_format($totalPrice, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.products-report-table tbody tr:hover {
    background: hsl(0 0% 15% / 1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var purchasesCtx = document.getElementById('purchasesChart');
    if (purchasesCtx && typeof Chart !== 'undefined') {
        new Chart(purchasesCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: '<?= Yii::t('common', 'Количество покупок') ?>',
                    data: <?= json_encode($chartTotalPurchases) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.6)',
                    borderColor: '#22c55e',
                    borderWidth: 2
                }, {
                    label: '<?= Yii::t('common', 'Выручка (руб.)') ?>',
                    data: <?= json_encode($chartTotalRevenue) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#e5e7eb' } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 1) {
                                        label += context.parsed.y.toLocaleString('ru-RU') + ' <?= Yii::t('common', 'руб.') ?>';
                                    } else {
                                        label += context.parsed.y.toLocaleString('ru-RU');
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
                            color: '#9ca3af',
                            callback: function(value) {
                                return value.toLocaleString('ru-RU') + ' <?= Yii::t('common', 'руб.') ?>';
                            }
                        },
                        grid: { drawOnChartArea: false }
                    },
                    x: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' }
                    }
                }
            }
        });
    }
    var topProductsCtx = document.getElementById('topProductsChart');
    if (topProductsCtx && typeof Chart !== 'undefined') {
        var topProductsData = <?= json_encode(array_map(function ($p) {
            return ['drop' => ['name' => $p['drop']->name], 'count' => $p['count']];
        }, $topProducts)) ?>;
        var topLabels = topProductsData.map(function(item) {
            var name = item.drop.name || '';
            return name.length > 20 ? name.substring(0, 20) + '...' : name;
        });
        var topCounts = topProductsData.map(function(item) { return item.count; });
        new Chart(topProductsCtx, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: '<?= Yii::t('common', 'Количество покупок') ?>',
                    data: topCounts,
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)', 'rgba(59, 130, 246, 0.8)', 'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)', 'rgba(139, 92, 246, 0.8)', 'rgba(52, 211, 153, 0.8)',
                        'rgba(251, 191, 36, 0.8)', 'rgba(244, 63, 94, 0.8)', 'rgba(99, 102, 241, 0.8)',
                        'rgba(156, 163, 175, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '<?= Yii::t('common', 'Покупок') ?>: ' + context.parsed.x.toLocaleString('ru-RU');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' }
                    },
                    y: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' }
                    }
                }
            }
        });
    }
});
</script>
