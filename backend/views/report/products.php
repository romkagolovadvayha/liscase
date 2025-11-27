<?php

use yii\helpers\Html;

/** @var array $data */
/** @var \common\models\box\Drop $drop */

$this->title = "Отчет по покупкам";

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
        $drop = \common\models\box\Drop::findOne($productData['drop_id']);
        if ($drop) {
            $count = $productData['count'];
            $revenue = $count * $drop->getRealPrice();
            $monthPurchases += $count;
            $monthRevenue += $revenue;
            
            // Собираем данные для топ продуктов
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

// Сортируем топ продуктов
usort($allProductsData, function($a, $b) {
    return $b['count'] - $a['count'];
});
$topProducts = array_slice($allProductsData, 0, 10);

$avgPurchasePrice = $totalAllPurchases > 0 ? $totalAllRevenue / $totalAllPurchases : 0;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>
<div class="products-report-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Общая статистика -->
        <div class="ds-card mb-4">
            <h2 class="mb-4">Общая статистика за <?= count($data) ?> месяца</h2>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalAllPurchases, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Всего покупок</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalAllRevenue, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Общая выручка (руб.)</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalUniqueProducts, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Уникальных предметов</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($avgPurchasePrice, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Средняя цена покупки (руб.)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="ds-card">
                    <h3 class="mb-3">Динамика покупок по месяцам</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="purchasesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ds-card">
                    <h3 class="mb-3">Топ-10 самых покупаемых предметов</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Топ продуктов -->
        <div class="ds-card mb-4">
            <h2 class="mb-4">Топ-10 самых популярных предметов</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Изображение</th>
                        <th>Предмет</th>
                        <th>Количество покупок</th>
                        <th>Выручка</th>
                        <th>Средняя цена</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $index = 1; foreach ($topProducts as $productData): ?>
                        <?php $drop = $productData['drop']; ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td>
                                <?php if ($drop->imageOrig): ?>
                                    <?= Html::img($drop->imageOrig->getImagePubUrl(false), [
                                        'width' => '40px',
                                        'height' => '40px',
                                        'loading' => 'lazy',
                                        'alt' => Html::encode($drop->name ?? ''),
                                        'style' => 'border-radius: 4px; object-fit: cover;'
                                    ]) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= Html::a(
                                    Html::encode($drop->name),
                                    ['/drop/update', 'id' => $drop->id],
                                    ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                ) ?>
                            </td>
                            <td><strong><?= number_format($productData['count'], 0, '.', ' ') ?></strong></td>
                            <td><strong><?= number_format($productData['revenue'], 0, '.', ' ') ?> руб.</strong></td>
                            <td><?= number_format($productData['revenue'] / $productData['count'], 0, '.', ' ') ?> руб.</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Детальная информация по месяцам -->
        <?php foreach ($data as $date => $_data): ?>
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <h2><?= Html::encode($_data['month']) ?> <?= date('Y', strtotime($date)) ?></h2>
            </div>
            <div class="ds-card__body">
                <?php
                $monthTotalPurchases = 0;
                $monthTotalRevenue = 0;
                foreach ($_data['products'] as $productData) {
                    $drop = \common\models\box\Drop::findOne($productData['drop_id']);
                    if ($drop) {
                        $monthTotalPurchases += $productData['count'];
                        $monthTotalRevenue += $productData['count'] * $drop->getRealPrice();
                    }
                }
                $monthAvgPrice = $monthTotalPurchases > 0 ? $monthTotalRevenue / $monthTotalPurchases : 0;
                ?>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Всего покупок</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= number_format($monthTotalPurchases, 0, '.', ' ') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Общая выручка</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= number_format($monthTotalRevenue, 0, '.', ' ') ?> руб.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Уникальных предметов</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= count($_data['products']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Средняя цена</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= number_format($monthAvgPrice, 0, '.', ' ') ?> руб.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Изображение</th>
                            <th>Предмет</th>
                            <th>Количество покупок</th>
                            <th>Цена за единицу</th>
                            <th>Общая сумма</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $index = 1;
                        foreach ($_data['products'] as $productData): 
                            $drop = \common\models\box\Drop::findOne($productData['drop_id']);
                            if (!$drop) continue;
                            $unitPrice = $drop->getRealPrice();
                            $totalPrice = $productData['count'] * $unitPrice;
                        ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td>
                                    <?php if ($drop->imageOrig): ?>
                                        <?= Html::img($drop->imageOrig->getImagePubUrl(false), [
                                            'width' => '40px',
                                            'height' => '40px',
                                            'loading' => 'lazy',
                                            'alt' => Html::encode($drop->name ?? ''),
                                            'style' => 'border-radius: 4px; object-fit: cover;'
                                        ]) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= Html::a(
                                        Html::encode($drop->name),
                                        ['/drop/update', 'id' => $drop->id],
                                        ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                    ) ?>
                                </td>
                                <td><strong><?= number_format($productData['count'], 0, '.', ' ') ?></strong></td>
                                <td><?= number_format($unitPrice, 0, '.', ' ') ?> руб.</td>
                                <td><strong><?= number_format($totalPrice, 0, '.', ' ') ?> руб.</strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // График динамики покупок
    const purchasesCtx = document.getElementById('purchasesChart');
    if (purchasesCtx) {
        new Chart(purchasesCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Количество покупок',
                    data: <?= json_encode($chartTotalPurchases) ?>,
                    backgroundColor: 'rgba(31, 158, 18, 0.6)',
                    borderColor: '#1f9e12',
                    borderWidth: 2
                }, {
                    label: 'Выручка (руб.)',
                    data: <?= json_encode($chartTotalRevenue) ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: '#007bff',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#f2f2f2'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 1) {
                                        label += context.parsed.y.toLocaleString('ru-RU') + ' руб.';
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
                        ticks: {
                            color: '#888'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
                            color: '#888',
                            callback: function(value) {
                                return value.toLocaleString('ru-RU') + ' руб.';
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    x: {
                        ticks: {
                            color: '#888'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
    }

    // График топ продуктов
    const topProductsCtx = document.getElementById('topProductsChart');
    if (topProductsCtx) {
        const topProductsData = <?= json_encode(array_slice($topProducts, 0, 10)) ?>;
        const topLabels = topProductsData.map(function(item) {
            const name = item.drop.name;
            return name.length > 20 ? name.substring(0, 20) + '...' : name;
        });
        const topCounts = topProductsData.map(function(item) {
            return item.count;
        });
        
        new Chart(topProductsCtx, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: 'Количество покупок',
                    data: topCounts,
                    backgroundColor: [
                        'rgba(31, 158, 18, 0.8)',
                        'rgba(0, 123, 255, 0.8)',
                        'rgba(243, 156, 18, 0.8)',
                        'rgba(226, 106, 106, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(241, 196, 15, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(149, 165, 166, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Покупок: ' + context.parsed.x.toLocaleString('ru-RU');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            color: '#888'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#888'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
    }
});
</script>
