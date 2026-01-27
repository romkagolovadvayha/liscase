<?php

use yii\helpers\Html;

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

// Подготовка данных для графиков
$chartServerLabels = [];
$chartServerReports = [];
$chartServerUsers = [];
$avgReportsPerUser = $totalUsers > 0 ? $totalReports / $totalUsers : 0;

foreach ($serverStats as $stat) {
    $chartServerLabels[] = $stat['server']['name'] ?? '';
    $chartServerReports[] = $stat['reports_count'];
    $chartServerUsers[] = $stat['users_count'];
}

// Топ пользователей с репортами
$topReportedUsers = array_slice($users, 0, 10, true);

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>
<div class="reports-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <?= \frontend\widgets\Alert::widget() ?>

        <!-- Общая статистика -->
        <div class="ds-card mb-4">
            <h2 class="mb-4">Общая статистика</h2>
<div class="row">
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalReports, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Всего репортов</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($totalUsers, 0, '.', ' ') ?></div>
                        <div class="ds-counter__label">Пользователей с репортами</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= number_format($avgReportsPerUser, 1, '.', ' ') ?></div>
                        <div class="ds-counter__label">Среднее репортов на пользователя</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="ds-counter">
                        <div class="ds-counter__value"><?= count($serverStats) ?></div>
                        <div class="ds-counter__label">Серверов</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="ds-card">
                    <h3 class="mb-3">Репорты по серверам</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="serverReportsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ds-card">
                    <h3 class="mb-3">Пользователи с репортами по серверам</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="serverUsersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Топ пользователей -->
        <div class="ds-card mb-4">
            <h2 class="mb-4">Топ-10 пользователей с наибольшим количеством репортов</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Пользователь</th>
                        <th>Steam ID</th>
                        <th>Количество репортов</th>
                        <th>Серверы</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $index = 1; foreach ($topReportedUsers as $userData): ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td>
                                <?= Html::a(
                                    Html::encode($userData['username']),
                                    ['/user/profile', 'userId' => $userData['user_id']],
                                    ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                ) ?>
                            </td>
                            <td>
                                <?= Html::a(
                                    $userData['steam_id'],
                                    'https://steamcommunity.com/profiles/' . $userData['steam_id'],
                                    ['target' => '_blank', 'class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                ) ?>
                            </td>
                            <td>
                                <span class="ds-badge <?= $userData['count'] >= 10 ? 'ds-badge--danger' : ($userData['count'] >= 5 ? 'ds-badge--warning' : 'ds-badge--info') ?>">
                                    <?= $userData['count'] ?>
                                </span>
                            </td>
                            <td>
                                <?php foreach ($userData['servers'] as $server): ?>
                                    <span class="ds-badge ds-badge--primary" style="margin-right: 0.25rem; margin-bottom: 0.25rem;">
                                        <?= Html::encode($server) ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Статистика по серверам -->
        <div class="ds-card mb-4">
            <h2 class="mb-4">Статистика по серверам</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Сервер</th>
                        <th>Количество репортов</th>
                        <th>Пользователей с репортами</th>
                        <th>Среднее репортов на пользователя</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($serverStats as $stat): ?>
                        <tr>
                            <td><strong><?= Html::encode($stat['server']['name'] ?? '') ?></strong></td>
                            <td>
                                <span class="ds-badge ds-badge--info">
                                    <?= number_format($stat['reports_count'], 0, '.', ' ') ?>
                                </span>
                            </td>
                            <td>
                                <span class="ds-badge ds-badge--primary">
                                    <?= number_format($stat['users_count'], 0, '.', ' ') ?>
                                </span>
                            </td>
                            <td>
                                <?= $stat['users_count'] > 0 
                                    ? number_format($stat['reports_count'] / $stat['users_count'], 1, '.', ' ') 
                                    : '0' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Полный список -->
        <div class="ds-card">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">Все пользователи с репортами</h5>
            </div>
            <div class="ds-card__body">
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $checkingProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'username',
                                                          'label'     => Yii::t('common', "Имя"),
                                                          'format'    => 'raw',
                            'value'     => function ($model) {
                                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model['user_id']]);
                                return Html::a(Html::encode($model['username']), $url, [
                                    'class' => 'ds-text--primary',
                                    'style' => 'text-decoration: none;'
                                ]);
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'steam_id',
                                                          'label'     => Yii::t('common', "Steam ID"),
                                                          'format'    => 'raw',
                            'value'     => function ($model) {
                                return Html::a(
                                    $model['steam_id'],
                                    'https://steamcommunity.com/profiles/' . $model['steam_id'],
                                    ['target' => '_blank', 'class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                );
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'count',
                                                          'label'     => Yii::t('common', "Кол-во репортов"),
                                                          'format'    => 'raw',
                                                          'options'   => ['width' => '150'],
                            'value'     => function ($model) {
                                $badgeClass = $model['count'] >= 10 
                                    ? 'ds-badge--danger' 
                                    : ($model['count'] >= 5 ? 'ds-badge--warning' : 'ds-badge--info');
                                return Html::tag('span', $model['count'], ['class' => 'ds-badge ' . $badgeClass]);
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'servers',
                            'options'   => ['width' => '200'],
                            'label'     => Yii::t('common', "Серверы"),
                                                          'format'    => 'raw',
                            'value'     => function ($model) {
                                $badges = [];
                                                                foreach ($model['servers'] as $server) {
                                    $badges[] = Html::tag('span', Html::encode($server), [
                                        'class' => 'ds-badge ds-badge--primary',
                                        'style' => 'margin-right: 0.25rem; margin-bottom: 0.25rem; display: inline-block;'
                                    ]);
                                                                }
                                return implode('', $badges);
                                                          },
                                                      ],
                                                  ],
                ]); ?>
            </div>
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
