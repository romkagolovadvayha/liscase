<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $users */
/** @var array $serverStats */
/** @var int $totalReports */
/** @var int $totalUsers */

$this->title = Yii::t('common', 'Репорты');

$checkingProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $users,
    'totalCount' => count($users),
    'sort' => [
        'attributes' => ['count'],
        'defaultOrder' => ['count' => SORT_DESC],
    ],
    'pagination' => [
        'pageSize' => 30,
    ],
]);

$chartServerLabels = [];
$chartServerReports = [];
$chartServerUsers = [];
$avgReportsPerUser = $totalUsers > 0 ? $totalReports / $totalUsers : 0;

foreach ($serverStats as $stat) {
    $chartServerLabels[] = $stat['server']['name'] ?? '';
    $chartServerReports[] = $stat['reports_count'];
    $chartServerUsers[] = $stat['users_count'];
}

$topReportedUsers = array_slice($users, 0, 10, true);

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>
<div class="reports-index-page w-full p-4 lg:p-6">
    <?= \frontend\widgets\Alert::widget() ?>

    <!-- Общая статистика -->
    <div class="reports-card mb-6 rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-4"><?= Yii::t('common', 'Общая статистика') ?></h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded bg-[hsl(0_0%_15%_/_1)] p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                <div class="text-xl font-semibold text-white"><?= number_format($totalReports, 0, '.', ' ') ?></div>
                <div class="text-xs text-gray-400"><?= Yii::t('common', 'Всего репортов') ?></div>
            </div>
            <div class="rounded bg-[hsl(0_0%_15%_/_1)] p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                <div class="text-xl font-semibold text-white"><?= number_format($totalUsers, 0, '.', ' ') ?></div>
                <div class="text-xs text-gray-400"><?= Yii::t('common', 'Пользователей с репортами') ?></div>
            </div>
            <div class="rounded bg-[hsl(0_0%_15%_/_1)] p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                <div class="text-xl font-semibold text-white"><?= number_format($avgReportsPerUser, 1, '.', ' ') ?></div>
                <div class="text-xs text-gray-400"><?= Yii::t('common', 'Среднее репортов на пользователя') ?></div>
            </div>
            <div class="rounded bg-[hsl(0_0%_15%_/_1)] p-3 border border-[hsl(0_0%_15.3%_/_1)]">
                <div class="text-xl font-semibold text-white"><?= count($serverStats) ?></div>
                <div class="text-xs text-gray-400"><?= Yii::t('common', 'Серверов') ?></div>
            </div>
        </div>
    </div>

    <!-- Графики -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="reports-card rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4">
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-3"><?= Yii::t('common', 'Репорты по серверам') ?></h3>
            <div class="relative h-[300px]">
                <canvas id="serverReportsChart"></canvas>
            </div>
        </div>
        <div class="reports-card rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4">
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-3"><?= Yii::t('common', 'Пользователи с репортами по серверам') ?></h3>
            <div class="relative h-[300px]">
                <canvas id="serverUsersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Топ пользователей -->
    <div class="reports-card mb-6 rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] overflow-hidden">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]"><?= Yii::t('common', 'Топ-10 пользователей с наибольшим количеством репортов') ?></h2>
        <div class="overflow-x-auto">
            <table class="table-auto w-full text-sm">
                <thead>
                    <tr>
                        <th class="<?= $headerCellClass ?>">#</th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Пользователь') ?></th>
                        <th class="<?= $headerCellClass ?>">Steam ID</th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Количество репортов') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Серверы') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $index = 1; foreach ($topReportedUsers as $userData): ?>
                    <tr>
                        <td class="<?= $bodyCellClass ?>"><?= $index++ ?></td>
                        <td class="<?= $bodyCellClass ?>">
                            <?= Html::a(Html::encode($userData['username']), '/profile/' . $userData['user_id'], ['class' => 'text-blue-400 hover:underline']) ?>
                        </td>
                        <td class="<?= $bodyCellClass ?>">
                            <?= Html::a($userData['steam_id'], 'https://steamcommunity.com/profiles/' . $userData['steam_id'], ['target' => '_blank', 'class' => 'text-blue-400 hover:underline']) ?>
                        </td>
                        <td class="<?= $bodyCellClass ?>">
                            <span class="ds-badge <?= $userData['count'] >= 10 ? 'ds-badge--danger' : ($userData['count'] >= 5 ? 'ds-badge--warning' : 'ds-badge--info') ?>"><?= $userData['count'] ?></span>
                        </td>
                        <td class="<?= $bodyCellClass ?>">
                            <?php foreach ($userData['servers'] as $server): ?>
                                <span class="ds-badge ds-badge--primary mr-1 mb-1 inline-block"><?= Html::encode($server) ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Статистика по серверам -->
    <div class="reports-card mb-6 rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] overflow-hidden">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]"><?= Yii::t('common', 'Статистика по серверам') ?></h2>
        <div class="overflow-x-auto">
            <table class="table-auto w-full text-sm">
                <thead>
                    <tr>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Сервер') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Количество репортов') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Пользователей с репортами') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Среднее репортов на пользователя') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serverStats as $stat): ?>
                    <tr>
                        <td class="<?= $bodyCellClass ?>"><strong><?= Html::encode($stat['server']['name'] ?? '') ?></strong></td>
                        <td class="<?= $bodyCellClass ?>"><span class="ds-badge ds-badge--info"><?= number_format($stat['reports_count'], 0, '.', ' ') ?></span></td>
                        <td class="<?= $bodyCellClass ?>"><span class="ds-badge ds-badge--primary"><?= number_format($stat['users_count'], 0, '.', ' ') ?></span></td>
                        <td class="<?= $bodyCellClass ?>"><?= $stat['users_count'] > 0 ? number_format($stat['reports_count'] / $stat['users_count'], 1, '.', ' ') : '0' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Полный список -->
    <div class="reports-card rounded-lg border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] overflow-hidden">
        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]"><?= Yii::t('common', 'Все пользователи с репортами') ?></h2>
        <div class="w-full">
            <?= \kartik\grid\GridView::widget([
                'dataProvider' => $checkingProvider,
                'tableOptions' => ['class' => 'table-auto w-full text-sm'],
                'options' => ['class' => 'admin-grid-view-dark'],
                'layout' => "{items}\n{pager}",
                'filterRowOptions' => ['style' => 'display: none;'],
                'bordered' => false,
                'striped' => false,
                'hover' => true,
                'columns' => [
                    [
                        'attribute' => 'username',
                        'label' => Yii::t('common', 'Имя'),
                        'format' => 'raw',
                        'headerOptions' => ['class' => $headerCellClass],
                        'contentOptions' => ['class' => $bodyCellClass],
                        'value' => function ($model) {
                            return Html::a(Html::encode($model['username']), '/profile/' . $model['user_id'], ['class' => 'text-blue-400 hover:underline']);
                        },
                    ],
                    [
                        'attribute' => 'steam_id',
                        'label' => Yii::t('common', 'Steam ID'),
                        'format' => 'raw',
                        'headerOptions' => ['class' => $headerCellClass],
                        'contentOptions' => ['class' => $bodyCellClass],
                        'value' => function ($model) {
                            return Html::a($model['steam_id'], 'https://steamcommunity.com/profiles/' . $model['steam_id'], ['target' => '_blank', 'class' => 'text-blue-400 hover:underline']);
                        },
                    ],
                    [
                        'attribute' => 'count',
                        'label' => Yii::t('common', 'Кол-во репортов'),
                        'format' => 'raw',
                        'headerOptions' => ['class' => $headerCellClass],
                        'contentOptions' => ['class' => $bodyCellClass],
                        'value' => function ($model) {
                            $badgeClass = $model['count'] >= 10 ? 'ds-badge--danger' : ($model['count'] >= 5 ? 'ds-badge--warning' : 'ds-badge--info');
                            return Html::tag('span', $model['count'], ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    [
                        'attribute' => 'servers',
                        'label' => Yii::t('common', 'Серверы'),
                        'format' => 'raw',
                        'headerOptions' => ['class' => $headerCellClass],
                        'contentOptions' => ['class' => $bodyCellClass],
                        'value' => function ($model) {
                            $badges = [];
                            foreach ($model['servers'] as $server) {
                                $badges[] = Html::tag('span', Html::encode($server), ['class' => 'ds-badge ds-badge--primary mr-1 mb-1 inline-block']);
                            }
                            return implode('', $badges);
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // График репортов по серверам
    const serverReportsCtx = document.getElementById('serverReportsChart');
    if (serverReportsCtx) {
        new Chart(serverReportsCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartServerLabels) ?>,
                datasets: [{
                    label: 'Количество репортов',
                    data: <?= json_encode($chartServerReports) ?>,
                    backgroundColor: 'rgba(226, 106, 106, 0.6)',
                    borderColor: '#e26a6a',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Репортов: ' + context.parsed.y.toLocaleString('ru-RU');
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

    // График пользователей с репортами по серверам
    const serverUsersCtx = document.getElementById('serverUsersChart');
    if (serverUsersCtx) {
        new Chart(serverUsersCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartServerLabels) ?>,
                datasets: [{
                    label: 'Пользователей с репортами',
                    data: <?= json_encode($chartServerUsers) ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: '#007bff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Пользователей: ' + context.parsed.y.toLocaleString('ru-RU');
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
});
</script>
