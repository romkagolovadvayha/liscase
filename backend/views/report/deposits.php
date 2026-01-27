<?php

use yii\helpers\Html;

/** @var array $data */
/** @var \common\models\invoice\Deposit $deposit */
/** @var \common\models\user\User $user */

$this->title = "Отчет по пополнениям";

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
<div class="deposits-report-page">
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
                        <div class="ds-counter__value"><?= number_format($totalAll, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Всего пополнений (руб.)</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalProfit, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Чистый доход (руб.)</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalUsers, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Уникальных пользователей</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalDeposits, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Всего платежей</div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                        <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Средний чек</div>
                        <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                            <?= number_format($avgDeposit, 0, '.', ' ') ?> руб.
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                        <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Средний депозит на пользователя</div>
                        <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                            <?= number_format($avgUserDeposit, 0, '.', ' ') ?> руб.
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                        <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Рентабельность</div>
                        <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                            <?= $totalAll > 0 ? number_format(($totalProfit / $totalAll) * 100, 1, '.', ' ') : 0 ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="ds-card">
                    <h3 class="mb-3">Динамика доходов и расходов</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="incomeExpenseChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ds-card">
                    <h3 class="mb-3">Распределение расходов</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="expensesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Детальная информация по месяцам -->
        <?php foreach ($data as $date => $_data): ?>
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <h2><?= Html::encode($_data['month']) ?> <?= date('Y', strtotime($date)) ?></h2>
            </div>
            <div class="ds-card__body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Всего за месяц</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= number_format($_data['total'], 0, '.', ' ') ?> руб.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Потрачено на скины</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= number_format($_data['skindrops'] ?? 0, 0, '.', ' ') ?> руб.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Расходы</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                <?= number_format(($expensesModerator + $expensesServers + $expensesInfrastructure), 0, '.', ' ') ?> руб.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                            <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Чистый доход</div>
                            <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600; color: #1f9e12;">
                                <?= number_format($_data['total'] - (($_data['skindrops'] ?? 0) + $expensesModerator + $expensesServers + $expensesInfrastructure), 0, '.', ' ') ?> руб.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="ds-badge ds-badge--info" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                            Человек пополнило: <strong><?= count($_data['users']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ds-badge ds-badge--info" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                            Всего платежей: <strong><?= count($_data['deposits']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ds-badge ds-badge--info" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                            Средний чек: <strong><?= count($_data['deposits']) > 0 ? number_format($_data['total'] / count($_data['deposits']), 0, '.', ' ') : 0 ?> руб.</strong>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table">
                <thead>
                <tr>
                            <th>#</th>
                            <th>Ник</th>
                            <th>Сумма пополнений</th>
                            <th>Количество платежей</th>
                            <th>Средний чек</th>
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
                                <td><?= $index++ ?></td>
                                <td>
                                    <?= Html::a(
                                        Html::encode($user->username),
                                        ['/user/profile', 'userId' => $user->id],
                                        ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                    ) ?>
                                </td>
                                <td><strong><?= number_format($dataUser['amount'], 0, '.', ' ') ?> руб.</strong></td>
                                <td><?= $depositCount ?></td>
                                <td><?= number_format($avgCheck, 0, '.', ' ') ?> руб.</td>
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
    // График доходов и расходов
    const incomeExpenseCtx = document.getElementById('incomeExpenseChart');
    if (incomeExpenseCtx) {
        new Chart(incomeExpenseCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Доходы',
                    data: <?= json_encode($chartTotals) ?>,
                    borderColor: '#1f9e12',
                    backgroundColor: 'rgba(31, 158, 18, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Расходы',
                    data: <?= json_encode($chartExpenses) ?>,
                    borderColor: '#e26a6a',
                    backgroundColor: 'rgba(226, 106, 106, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Чистая прибыль',
                    data: <?= json_encode($chartProfit) ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
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
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#888',
                            callback: function(value) {
                                return value.toLocaleString('ru-RU') + ' руб.';
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
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

    // График распределения расходов
    const expensesCtx = document.getElementById('expensesChart');
    if (expensesCtx) {
        const totalExpenses = <?= $totalSkindrops ?> + <?= $expensesModerator * count($data) ?> + <?= $expensesServers * count($data) ?> + <?= $expensesInfrastructure * count($data) ?>;
        new Chart(expensesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Скины', 'Зарплата модератору', 'Сервера', 'Инфраструктура'],
                datasets: [{
                    data: [
                        <?= $totalSkindrops ?>,
                        <?= $expensesModerator * count($data) ?>,
                        <?= $expensesServers * count($data) ?>,
                        <?= $expensesInfrastructure * count($data) ?>
                    ],
                    backgroundColor: [
                        '#e26a6a',
                        '#f39c12',
                        '#007bff',
                        '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#f2f2f2',
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const percentage = ((value / totalExpenses) * 100).toFixed(1);
                                return label + ': ' + value.toLocaleString('ru-RU') + ' руб. (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
