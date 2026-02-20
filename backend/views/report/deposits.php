<?php

use yii\helpers\Html;

/** @var array $data */
/** @var \common\models\invoice\Deposit $deposit */
/** @var \common\models\user\User $user */

$this->title = Yii::t('common', 'Отчет по пополнениям');
$this->params['contentClass'] = 'content-no-padding';

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

// Подготовка данных для графиков
$chartLabels = [];
$chartTotals = [];
$chartExpenses = [];
$chartProfit = [];
$totalAll = 0;
$totalSkindrops = 0;
$totalUsers = 0;
$totalDeposits = 0;
$expensesModerator = 60000;
$expensesServers = 30000;
$expensesInfrastructure = 15000;

foreach ($data as $date => $_data) {
    $chartLabels[] = $_data['month'];
    $chartTotals[] = $_data['total'];
    $chartExpenses[] = ($_data['skindrops'] ?? 0) + $expensesModerator + $expensesServers + $expensesInfrastructure;
    $chartProfit[] = $_data['total'] - (($_data['skindrops'] ?? 0) + $expensesModerator + $expensesServers + $expensesInfrastructure);
    $totalAll += $_data['total'];
    $totalSkindrops += ($_data['skindrops'] ?? 0);
    $totalUsers += count($_data['users']);
    $totalDeposits += count($_data['deposits']);
}

$totalExpenses = $totalSkindrops + ($expensesModerator * count($data)) + ($expensesServers * count($data)) + ($expensesInfrastructure * count($data));
$totalProfit = $totalAll - $totalExpenses;
$avgDeposit = $totalDeposits > 0 ? $totalAll / $totalDeposits : 0;
$avgUserDeposit = $totalUsers > 0 ? $totalAll / $totalUsers : 0;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>
<div class="deposits-report-page w-full p-4 lg:p-6">
    <!-- Общая статистика -->
    <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Общая статистика за') ?> <?= count($data) ?> <?= Yii::t('common', 'мес.') ?></h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Всего пополнений (руб.)') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalAll, 0, '.', ' ') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Чистый доход (руб.)') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalProfit, 0, '.', ' ') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Уникальных пользователей') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalUsers, 0, '.', ' ') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Всего платежей') ?></div>
                    <div class="text-white text-xl font-semibold"><?= number_format($totalDeposits, 0, '.', ' ') ?></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Средний чек') ?></div>
                    <div class="text-white text-lg font-semibold"><?= number_format($avgDeposit, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Средний депозит на пользователя') ?></div>
                    <div class="text-white text-lg font-semibold"><?= number_format($avgUserDeposit, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-4 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs uppercase tracking-wide mb-1"><?= Yii::t('common', 'Рентабельность') ?></div>
                    <div class="text-white text-lg font-semibold"><?= $totalAll > 0 ? number_format(($totalProfit / $totalAll) * 100, 1, '.', ' ') : 0 ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Графики -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Динамика доходов и расходов') ?></h3>
            </div>
            <div class="p-4" style="position: relative; height: 300px;">
                <canvas id="incomeExpenseChart"></canvas>
            </div>
        </div>
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Распределение расходов') ?></h3>
            </div>
            <div class="p-4" style="position: relative; height: 300px;">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Детальная информация по месяцам -->
    <?php foreach ($data as $date => $_data): ?>
    <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Html::encode($_data['month']) ?> <?= date('Y', strtotime($date)) ?></h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Всего за месяц') ?></div>
                    <div class="text-white font-semibold"><?= number_format($_data['total'], 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Потрачено на скины') ?></div>
                    <div class="text-white font-semibold"><?= number_format($_data['skindrops'] ?? 0, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Расходы') ?></div>
                    <div class="text-white font-semibold"><?= number_format($expensesModerator + $expensesServers + $expensesInfrastructure, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                </div>
                <div class="bg-[hsl(0_0%_15.3%_/_1)] rounded-lg p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                    <div class="text-gray-400 text-xs mb-1"><?= Yii::t('common', 'Чистый доход') ?></div>
                    <div class="text-green-400 font-semibold"><?= number_format($_data['total'] - (($_data['skindrops'] ?? 0) + $expensesModerator + $expensesServers + $expensesInfrastructure), 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3 mb-4">
                <span class="px-3 py-1.5 rounded text-sm bg-[hsl(0_0%_25%_/_1)] text-gray-300"><?= Yii::t('common', 'Человек пополнило') ?>: <strong><?= count($_data['users']) ?></strong></span>
                <span class="px-3 py-1.5 rounded text-sm bg-[hsl(0_0%_25%_/_1)] text-gray-300"><?= Yii::t('common', 'Всего платежей') ?>: <strong><?= count($_data['deposits']) ?></strong></span>
                <span class="px-3 py-1.5 rounded text-sm bg-[hsl(0_0%_25%_/_1)] text-gray-300"><?= Yii::t('common', 'Средний чек') ?>: <strong><?= count($_data['deposits']) > 0 ? number_format($_data['total'] / count($_data['deposits']), 0, '.', ' ') : 0 ?> <?= Yii::t('common', 'руб.') ?></strong></span>
            </div>

            <div class="overflow-x-auto rounded-lg border border-[hsl(0_0%_15.3%_/_1)]">
                <table class="w-full text-sm table-auto deposits-report-table">
                    <thead>
                        <tr>
                            <th class="<?= $headerCellClass ?>">#</th>
                            <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Ник') ?></th>
                            <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Сумма пополнений') ?></th>
                            <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Количество платежей') ?></th>
                            <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Средний чек') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $userDepositCounts = [];
                        foreach ($_data['deposits'] as $deposit) {
                            if (!isset($userDepositCounts[$deposit->user_id])) {
                                $userDepositCounts[$deposit->user_id] = 0;
                            }
                            $userDepositCounts[$deposit->user_id]++;
                        }
                        $index = 1;
                        foreach ($_data['users'] as $dataUser):
                            $user = $dataUser['user'];
                            $depositCount = $userDepositCounts[$user->id] ?? 0;
                            $avgCheck = $depositCount > 0 ? $dataUser['amount'] / $depositCount : 0;
                        ?>
                        <tr>
                            <td class="<?= $bodyCellClass ?>"><?= $index++ ?></td>
                            <td class="<?= $bodyCellClass ?>">
                                <?= Html::a(Html::encode($user->username), ['/user/profile', 'userId' => $user->id], ['class' => 'text-blue-400 hover:underline']) ?>
                            </td>
                            <td class="<?= $bodyCellClass ?>"><strong><?= number_format($dataUser['amount'], 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></strong></td>
                            <td class="<?= $bodyCellClass ?>"><?= $depositCount ?></td>
                            <td class="<?= $bodyCellClass ?>"><?= number_format($avgCheck, 0, '.', ' ') ?> <?= Yii::t('common', 'руб.') ?></td>
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
.deposits-report-table tbody tr:hover {
    background: hsl(0 0% 15% / 1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var incomeExpenseCtx = document.getElementById('incomeExpenseChart');
    if (incomeExpenseCtx && typeof Chart !== 'undefined') {
        new Chart(incomeExpenseCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: '<?= Yii::t('common', 'Доходы') ?>',
                    data: <?= json_encode($chartTotals) ?>,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: '<?= Yii::t('common', 'Расходы') ?>',
                    data: <?= json_encode($chartExpenses) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: '<?= Yii::t('common', 'Чистая прибыль') ?>',
                    data: <?= json_encode($chartProfit) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#e5e7eb' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#9ca3af',
                            callback: function(value) {
                                return value.toLocaleString('ru-RU') + ' <?= Yii::t('common', 'руб.') ?>';
                            }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' }
                    },
                    x: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' }
                    }
                }
            }
        });
    }
    var expensesCtx = document.getElementById('expensesChart');
    if (expensesCtx && typeof Chart !== 'undefined') {
        var totalExpenses = <?= $totalSkindrops ?> + <?= $expensesModerator * count($data) ?> + <?= $expensesServers * count($data) ?> + <?= $expensesInfrastructure * count($data) ?>;
        new Chart(expensesCtx, {
            type: 'doughnut',
            data: {
                labels: ['<?= Yii::t('common', 'Скины') ?>', '<?= Yii::t('common', 'Зарплата модератору') ?>', '<?= Yii::t('common', 'Сервера') ?>', '<?= Yii::t('common', 'Инфраструктура') ?>'],
                datasets: [{
                    data: [
                        <?= $totalSkindrops ?>,
                        <?= $expensesModerator * count($data) ?>,
                        <?= $expensesServers * count($data) ?>,
                        <?= $expensesInfrastructure * count($data) ?>
                    ],
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#e5e7eb', padding: 15 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = context.parsed || 0;
                                var pct = totalExpenses ? ((value / totalExpenses) * 100).toFixed(1) : 0;
                                return (context.label || '') + ': ' + value.toLocaleString('ru-RU') + ' <?= Yii::t('common', 'руб.') ?> (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
