<?php

use yii\web\View;
use frontend\widgets\Alert;
use yii\helpers\Html;
use yii\db\Expression;
use common\models\user\User;
use common\models\user\UserPayoutSkins;
use common\models\profit\Profit;
use common\models\user\UserBalance;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Отчет по скиндропсам");

// Кэшируем только агрегаты: сырые строки отчёта занимали десятки мегабайт в Redis.
$cacheKey = 'skindrops_report_data_v6';
$data = Yii::$app->cache->get($cacheKey);

if ($data === false) {
    set_time_limit(120);

    $successStatus = (int) UserPayoutSkins::STATUS_SUCCESS;
    $rejectStatus = (int) UserPayoutSkins::STATUS_REJECT;
    $newStatus = (int) UserPayoutSkins::STATUS_NEW;
    $waitStatus = (int) UserPayoutSkins::STATUS_WAIT;
    // Все тяжёлые вычисления выполняет БД: в PHP попадают только агрегаты.
    $itemsQuery = UserPayoutSkins::find()->alias('payout');
    $itemSummary = (clone $itemsQuery)
        ->select([
            'totalItems' => new Expression('COUNT(*)'),
            'totalAmount' => new Expression('COALESCE(SUM(payout.amount), 0)'),
            'receivedCount' => new Expression("SUM(CASE WHEN payout.status = {$successStatus} THEN 1 ELSE 0 END)"),
            'receivedAmount' => new Expression("COALESCE(SUM(CASE WHEN payout.status = {$successStatus} THEN payout.amount ELSE 0 END), 0)"),
            'timeoutCount' => new Expression("SUM(CASE WHEN payout.status = {$rejectStatus} THEN 1 ELSE 0 END)"),
            'timeoutAmount' => new Expression("COALESCE(SUM(CASE WHEN payout.status = {$rejectStatus} THEN payout.amount ELSE 0 END), 0)"),
            'newCount' => new Expression("SUM(CASE WHEN payout.status = {$newStatus} THEN 1 ELSE 0 END)"),
            'sentCount' => new Expression("SUM(CASE WHEN payout.status IN ({$waitStatus}, {$newStatus}) THEN 1 ELSE 0 END)"),
            'sentAmount' => new Expression("COALESCE(SUM(CASE WHEN payout.status IN ({$waitStatus}, {$newStatus}) THEN payout.amount ELSE 0 END), 0)"),
        ])
        ->asArray()
        ->one();

    $itemsByDate = [];
    $itemDateRows = (clone $itemsQuery)
        ->select([
            'report_date' => new Expression('DATE(payout.created_at)'),
            'total' => new Expression('COALESCE(SUM(payout.amount), 0)'),
            'received' => new Expression("COALESCE(SUM(CASE WHEN payout.status = {$successStatus} THEN payout.amount ELSE 0 END), 0)"),
            'timeout' => new Expression("COALESCE(SUM(CASE WHEN payout.status = {$rejectStatus} THEN payout.amount ELSE 0 END), 0)"),
            'sent' => new Expression("COALESCE(SUM(CASE WHEN payout.status IN ({$waitStatus}, {$newStatus}) THEN payout.amount ELSE 0 END), 0)"),
            'new' => new Expression("SUM(CASE WHEN payout.status = {$newStatus} THEN 1 ELSE 0 END)"),
            'count' => new Expression('COUNT(*)'),
            'count_received' => new Expression("SUM(CASE WHEN payout.status = {$successStatus} THEN 1 ELSE 0 END)"),
            'count_timeout' => new Expression("SUM(CASE WHEN payout.status = {$rejectStatus} THEN 1 ELSE 0 END)"),
            'count_sent' => new Expression("SUM(CASE WHEN payout.status IN ({$waitStatus}, {$newStatus}) THEN 1 ELSE 0 END)"),
        ])
        ->groupBy(new Expression('DATE(payout.created_at)'))
        ->orderBy(['report_date' => SORT_DESC])
        ->limit(30)
        ->asArray()
        ->all();
    foreach ($itemDateRows as $row) {
        $date = (string) $row['report_date'];
        unset($row['report_date']);
        $itemsByDate[$date] = array_map('floatval', $row);
        foreach (['new', 'count', 'count_received', 'count_timeout', 'count_sent'] as $countKey) {
            $itemsByDate[$date][$countKey] = (int) $row[$countKey];
        }
    }

    $topUsers = (clone $itemsQuery)
        ->select([
            'user_id' => 'payout.user_id',
            'count' => new Expression('COUNT(*)'),
            'amount' => new Expression('COALESCE(SUM(payout.amount), 0)'),
        ])
        ->where(['payout.status' => $successStatus])
        ->andWhere(['not', ['payout.user_id' => null]])
        ->groupBy(['payout.user_id'])
        ->orderBy(['amount' => SORT_DESC])
        ->limit(10)
        ->asArray()
        ->all();

    $topItems = (clone $itemsQuery)
        ->select([
            'name' => 'payout.name',
            'count' => new Expression('COUNT(*)'),
            'amount' => new Expression('COALESCE(SUM(payout.amount), 0)'),
        ])
        ->where(['payout.status' => $successStatus])
        ->groupBy(['payout.name'])
        ->orderBy(['amount' => SORT_DESC])
        ->limit(10)
        ->asArray()
        ->all();
    foreach ($topItems as &$topItem) {
        $topItem['name'] = trim((string) $topItem['name']) !== '' ? $topItem['name'] : Yii::t('common', 'Неизвестно');
        $topItem['count'] = (int) $topItem['count'];
        $topItem['amount'] = (float) $topItem['amount'];
    }
    unset($topItem);

    $byType = ['rust' => 0, 'cs2' => 0];
    $byTypeAmount = ['rust' => 0.0, 'cs2' => 0.0];
    $typeRows = (clone $itemsQuery)
        ->select([
            'type' => 'payout.type',
            'count' => new Expression('COUNT(*)'),
            'amount' => new Expression("COALESCE(SUM(CASE WHEN payout.status = {$successStatus} THEN payout.amount ELSE 0 END), 0)"),
        ])
        ->where(['payout.type' => array_keys($byType)])
        ->groupBy(['payout.type'])
        ->asArray()
        ->all();
    foreach ($typeRows as $row) {
        $type = (string) $row['type'];
        $byType[$type] = (int) $row['count'];
        $byTypeAmount[$type] = (float) $row['amount'];
    }

    $transfersQuery = Profit::find()
        ->alias('profit')
        ->innerJoin(['balance' => UserBalance::tableName()], 'balance.id = profit.user_balance_id')
        ->where([
            'profit.type' => Profit::TYPE_TRANSFER_SKINS,
            'profit.status' => 1,
            'balance.type' => UserBalance::TYPE_PERSONAL,
        ]);

    $transferSummary = (clone $transfersQuery)
        ->select([
            'count' => new Expression('COUNT(*)'),
            'amount' => new Expression('COALESCE(SUM(profit.amount), 0)'),
        ], 'STRAIGHT_JOIN')
        ->asArray()
        ->one();

    $transfersByDate = [];
    $transferDateRows = (clone $transfersQuery)
        ->select([
            'report_date' => new Expression('DATE(profit.created_at)'),
            'count' => new Expression('COUNT(*)'),
            'amount' => new Expression('COALESCE(SUM(profit.amount), 0)'),
        ], 'STRAIGHT_JOIN')
        ->groupBy(new Expression('DATE(profit.created_at)'))
        ->orderBy(['report_date' => SORT_DESC])
        ->limit(30)
        ->asArray()
        ->all();
    foreach ($transferDateRows as $row) {
        $transfersByDate[(string) $row['report_date']] = [
            'count' => (int) $row['count'],
            'amount' => (float) $row['amount'],
        ];
    }

    $topTransferUsers = (clone $transfersQuery)
        ->select([
            'user_id' => 'balance.user_id',
            'count' => new Expression('COUNT(*)'),
            'amount' => new Expression('COALESCE(SUM(profit.amount), 0)'),
        ], 'STRAIGHT_JOIN')
        ->andWhere(['not', ['balance.user_id' => null]])
        ->groupBy(['balance.user_id'])
        ->orderBy(['amount' => SORT_DESC])
        ->limit(10)
        ->asArray()
        ->all();

    $attachUsers = static function (array $rows): array {
        $userIds = array_map('intval', array_column($rows, 'user_id'));
        $users = User::find()->where(['id' => $userIds])->indexBy('id')->all();
        foreach ($rows as &$row) {
            $row['user_id'] = (int) $row['user_id'];
            $row['count'] = (int) $row['count'];
            $row['amount'] = (float) $row['amount'];
            if (isset($users[$row['user_id']])) {
                $row['user'] = $users[$row['user_id']];
            }
        }
        unset($row);

        return $rows;
    };
    $topUsers = $attachUsers($topUsers);
    $topTransferUsers = $attachUsers($topTransferUsers);

    $statusCounts = [
        'sent' => (int) ($itemSummary['sentCount'] ?? 0),
        'received' => (int) ($itemSummary['receivedCount'] ?? 0),
        'timeout' => (int) ($itemSummary['timeoutCount'] ?? 0),
        'new' => (int) ($itemSummary['newCount'] ?? 0),
    ];
    $totalItems = (int) ($itemSummary['totalItems'] ?? 0);
    $totalAmount = (float) ($itemSummary['totalAmount'] ?? 0);
    $receivedAmount = (float) ($itemSummary['receivedAmount'] ?? 0);
    $timeoutAmount = (float) ($itemSummary['timeoutAmount'] ?? 0);
    $sentAmount = (float) ($itemSummary['sentAmount'] ?? 0);
    $transfersCount = (int) ($transferSummary['count'] ?? 0);
    $totalTransfers = (float) ($transferSummary['amount'] ?? 0);
    $last30Days = $itemsByDate;

    // Объединяем даты для переводов с датами скиндропсов
    $allDates = array_unique(array_merge(array_keys($itemsByDate), array_keys($transfersByDate)));
    krsort($allDates);
    $last30DaysWithTransfers = [];
    foreach (array_slice($allDates, 0, 30) as $date) {
        $last30DaysWithTransfers[$date] = array_merge(
            $itemsByDate[$date] ?? [
                'total' => 0, 'received' => 0, 'timeout' => 0, 'sent' => 0, 'new' => 0,
                'count' => 0, 'count_received' => 0, 'count_timeout' => 0, 'count_sent' => 0,
            ],
            [
                'transfers_count' => $transfersByDate[$date]['count'] ?? 0,
                'transfers_amount' => $transfersByDate[$date]['amount'] ?? 0,
            ]
        );
    }
    
    $data = [
        'itemsByDate' => $itemsByDate,
        'last30Days' => $last30Days,
        'last30DaysWithTransfers' => $last30DaysWithTransfers,
        'statusCounts' => $statusCounts,
        'totalAmount' => $totalAmount,
        'receivedAmount' => $receivedAmount,
        'timeoutAmount' => $timeoutAmount,
        'sentAmount' => $sentAmount,
        'topUsers' => $topUsers,
        'topItems' => $topItems,
        'byType' => $byType,
        'byTypeAmount' => $byTypeAmount,
        'transfersByDate' => $transfersByDate,
        'totalTransfers' => $totalTransfers,
        'transfersCount' => $transfersCount,
        'topTransferUsers' => $topTransferUsers,
        'totalItems' => $totalItems,
    ];
    
    Yii::$app->cache->set($cacheKey, $data, 600); // 10 минут
}

extract($data);

// Подготовка данных для графиков
$chartLabels = [];
$chartTotal = [];
$chartReceived = [];
$chartTimeout = [];
$chartCount = [];
$chartCountReceived = [];
$chartCountTimeout = [];
$chartTransfers = [];
$chartTransfersCount = [];

foreach ($last30DaysWithTransfers as $date => $dayData) {
    $chartLabels[] = $date;
    $chartTotal[] = round($dayData['total'], 2);
    $chartReceived[] = round($dayData['received'], 2);
    $chartTimeout[] = round($dayData['timeout'], 2);
    $chartCount[] = $dayData['count'];
    $chartCountReceived[] = $dayData['count_received'];
    $chartCountTimeout[] = $dayData['count_timeout'];
    $chartTransfers[] = round($dayData['transfers_amount'] ?? 0, 2);
    $chartTransfersCount[] = $dayData['transfers_count'] ?? 0;
}

$successRate = $totalItems > 0 ? ($statusCounts['received'] / $totalItems) * 100 : 0;
$avgItemPrice = $statusCounts['received'] > 0 ? $receivedAmount / $statusCounts['received'] : 0;
$efficiency = $totalAmount > 0 ? ($receivedAmount / $totalAmount) * 100 : 0;
$lossRate = $totalAmount > 0 ? ($timeoutAmount / $totalAmount) * 100 : 0;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content skindrops-report-page">
    <?= Alert::widget(); ?>

    <!-- Общая статистика -->
    <div class="ds-card mb-4">
        <h2 class="mb-4">Общая статистика</h2>
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $totalItems ?></div>
                    <div class="ds-counter__label">Всего операций</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $statusCounts['received'] ?></div>
                    <div class="ds-counter__label">Успешно получено</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value <?= $statusCounts['timeout'] > 0 ? 'ds-text--danger' : '' ?>"><?= $statusCounts['timeout'] ?></div>
                    <div class="ds-counter__label">Не получено</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $statusCounts['sent'] ?></div>
                    <div class="ds-counter__label">В процессе</div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= number_format($totalAmount, 2, '.', ' ') ?> RUB</div>
                    <div class="ds-counter__label">Общая сумма</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= number_format($receivedAmount, 2, '.', ' ') ?> RUB</div>
                    <div class="ds-counter__label">Получено</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value <?= $timeoutAmount > 0 ? 'ds-text--danger' : '' ?>"><?= number_format($timeoutAmount, 2, '.', ' ') ?> RUB</div>
                    <div class="ds-counter__label">Не получено (потеряно)</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= number_format($successRate, 1, '.', ' ') ?>%</div>
                    <div class="ds-counter__label">Процент успеха</div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Средняя цена предмета</div>
                    <div class="ds-text--primary skindrops-report-metric__value">
                        <?= number_format($avgItemPrice, 2, '.', ' ') ?> руб.
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Эффективность</div>
                    <div class="ds-text--primary skindrops-report-metric__value">
                        <?= number_format($efficiency, 1, '.', ' ') ?>%
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Потери</div>
                    <div class="ds-text--danger skindrops-report-metric__value">
                        <?= number_format($lossRate, 1, '.', ' ') ?>%
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">В процессе</div>
                    <div class="ds-text--info skindrops-report-metric__value">
                        <?= number_format($sentAmount, 2, '.', ' ') ?> руб.
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Переводов в магазин</div>
                    <div class="ds-text--primary skindrops-report-metric__value">
                        <?= $transfersCount ?> операций
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Сумма переводов</div>
                    <div class="ds-text--success skindrops-report-metric__value">
                        <?= number_format($totalTransfers, 2, '.', ' ') ?> руб.
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Средний перевод</div>
                    <div class="ds-text--primary skindrops-report-metric__value">
                        <?= $transfersCount > 0 ? number_format($totalTransfers / $transfersCount, 2, '.', ' ') : 0 ?> руб.
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">Rust</div>
                    <div class="ds-text--primary skindrops-report-metric__value">
                        <?= $byType['rust'] ?> операций (<?= number_format($byTypeAmount['rust'], 2, '.', ' ') ?> руб.)
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ds-card skindrops-report-metric">
                    <div class="ds-text--secondary skindrops-report-metric__label">CS2</div>
                    <div class="ds-text--primary skindrops-report-metric__value">
                        <?= $byType['cs2'] ?> операций (<?= number_format($byTypeAmount['cs2'], 2, '.', ' ') ?> руб.)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Графики -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Динамика по дням (последние 30 дней)</h2>
                </div>
                <div class="ds-card__body">
                    <div class="skindrops-report-chart">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Распределение по статусам</h2>
                </div>
                <div class="ds-card__body">
                    <div class="skindrops-report-chart">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Количество операций по дням</h2>
                </div>
                <div class="ds-card__body">
                    <div class="skindrops-report-chart">
                        <canvas id="countChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Распределение по типам игр</h2>
                </div>
                <div class="ds-card__body">
                    <div class="skindrops-report-chart">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Переводы в магазин по дням</h2>
                </div>
                <div class="ds-card__body">
                    <div class="skindrops-report-chart">
                        <canvas id="transfersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Топ пользователей -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Топ-10 пользователей (вывод скинов)</h2>
                </div>
                <div class="ds-card__body">
                    <div class="table-responsive">
                        <table class="table" aria-label="Топ-10 пользователей по выводу скинов">
                            <thead>
                                <tr>
                                    <th>Пользователь</th>
                                    <th>Операций</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topUsers)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center ds-text--secondary">Нет данных</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topUsers as $index => $userData): ?>
                                        <tr>
                                            <td>
                                                <?php if (isset($userData['user'])): ?>
                                                    <?= Html::a(
                                                        Html::encode($userData['user']->username),
                                                        '/profile/' . $userData['user']->id,
                                                        ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                                    ) ?>
                                                <?php else: ?>
                                                    <span class="ds-text--secondary">ID: <?= $userData['user_id'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $userData['count'] ?></td>
                                            <td><?= number_format($userData['amount'], 2, '.', ' ') ?> RUB</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Топ-10 предметов</h2>
                </div>
                <div class="ds-card__body">
                    <div class="table-responsive">
                        <table class="table" aria-label="Топ-10 предметов">
                            <thead>
                                <tr>
                                    <th>Предмет</th>
                                    <th>Операций</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topItems)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center ds-text--secondary">Нет данных</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topItems as $item): ?>
                                        <tr>
                                            <td><?= Html::encode($item['name']) ?></td>
                                            <td><?= $item['count'] ?></td>
                                            <td><?= number_format($item['amount'], 2, '.', ' ') ?> RUB</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Топ пользователей по переводам -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h2 class="ds-card__header-title">Топ-10 пользователей по переводам в магазин</h2>
                </div>
                <div class="ds-card__body">
                    <div class="table-responsive">
                        <table class="table" aria-label="Топ-10 пользователей по переводам в магазин">
                            <thead>
                                <tr>
                                    <th>Пользователь</th>
                                    <th>Переводов</th>
                                    <th>Сумма</th>
                                    <th>Средний перевод</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topTransferUsers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center ds-text--secondary">Нет данных</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topTransferUsers as $userData): ?>
                                        <tr>
                                            <td>
                                                <?php if (isset($userData['user'])): ?>
                                                    <?= Html::a(
                                                        Html::encode($userData['user']->username),
                                                        '/profile/' . $userData['user']->id,
                                                        ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                                    ) ?>
                                                <?php else: ?>
                                                    <span class="ds-text--secondary">ID: <?= $userData['user_id'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $userData['count'] ?></td>
                                            <td><?= number_format($userData['amount'], 2, '.', ' ') ?> RUB</td>
                                            <td><?= number_format($userData['amount'] / $userData['count'], 2, '.', ' ') ?> RUB</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Детальная таблица по датам -->
    <div class="ds-card">
        <div class="ds-card__header">
            <h2 class="ds-card__header-title">Детальная статистика по датам (последние 30 дней)</h2>
        </div>
        <div class="ds-card__body">
            <div class="table-responsive">
                <table class="table" aria-label="Детальная статистика по датам за последние 30 дней">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Операций</th>
                                    <th>Общая сумма</th>
                                    <th>Получено</th>
                                    <th>Не получено</th>
                                    <th>В процессе</th>
                                    <th>Переводов</th>
                                    <th>Сумма переводов</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($last30DaysWithTransfers)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center ds-text--secondary">Нет данных</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($last30DaysWithTransfers as $date => $dayData): ?>
                                        <tr>
                                            <td><?= Html::encode($date) ?></td>
                                            <td><?= $dayData['count'] ?></td>
                                            <td><?= number_format($dayData['total'], 2, '.', ' ') ?> RUB</td>
                                            <td class="ds-text--success"><?= $dayData['count_received'] ?> (<?= number_format($dayData['received'], 2, '.', ' ') ?> RUB)</td>
                                            <td class="ds-text--danger"><?= $dayData['count_timeout'] ?> (<?= number_format($dayData['timeout'], 2, '.', ' ') ?> RUB)</td>
                                            <td class="ds-text--info"><?= $dayData['count_sent'] ?> (<?= number_format($dayData['sent'], 2, '.', ' ') ?> RUB)</td>
                                            <td class="ds-text--warning"><?= $dayData['transfers_count'] ?></td>
                                            <td class="ds-text--warning"><?= number_format($dayData['transfers_amount'], 2, '.', ' ') ?> RUB</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // График динамики по дням
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Общая сумма',
                    data: <?= json_encode($chartTotal) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'Получено',
                    data: <?= json_encode($chartReceived) ?>,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'Не получено',
                    data: <?= json_encode($chartTimeout) ?>,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.1
                },
                {
                    label: 'Переводы в магазин',
                    data: <?= json_encode($chartTransfers) ?>,
                    borderColor: 'rgb(251, 146, 60)',
                    backgroundColor: 'rgba(251, 146, 60, 0.1)',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#e5e7eb'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9ca3af'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#9ca3af'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });

    // График распределения по статусам
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Получено', 'Не получено', 'В процессе'],
            datasets: [{
                data: [
                    <?= $statusCounts['received'] ?>,
                    <?= $statusCounts['timeout'] ?>,
                    <?= $statusCounts['sent'] ?>
                ],
                backgroundColor: [
                    'rgb(34, 197, 94)',
                    'rgb(239, 68, 68)',
                    'rgb(59, 130, 246)'
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
                        color: '#e5e7eb'
                    }
                }
            }
        }
    });

    // График количества операций
    const countCtx = document.getElementById('countChart').getContext('2d');
    new Chart(countCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Всего операций',
                    data: <?= json_encode($chartCount) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Получено',
                    data: <?= json_encode($chartCountReceived) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                },
                {
                    label: 'Не получено',
                    data: <?= json_encode($chartCountTimeout) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.5)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#e5e7eb'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9ca3af',
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#9ca3af'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });

    // График по типам игр
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    new Chart(typeCtx, {
        type: 'bar',
        data: {
            labels: ['Rust', 'CS2'],
            datasets: [
                {
                    label: 'Количество операций',
                    data: [<?= $byType['rust'] ?>, <?= $byType['cs2'] ?>],
                    backgroundColor: 'rgba(251, 146, 60, 0.5)',
                    borderColor: 'rgb(251, 146, 60)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Сумма (RUB)',
                    data: [<?= round($byTypeAmount['rust'], 2) ?>, <?= round($byTypeAmount['cs2'], 2) ?>],
                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#e5e7eb'
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        color: '#9ca3af'
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
                        color: '#9ca3af'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // График переводов в магазин
    const transfersCtx = document.getElementById('transfersChart').getContext('2d');
    new Chart(transfersCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Количество переводов',
                    data: <?= json_encode($chartTransfersCount) ?>,
                    backgroundColor: 'rgba(251, 146, 60, 0.5)',
                    borderColor: 'rgb(251, 146, 60)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Сумма переводов (RUB)',
                    data: <?= json_encode($chartTransfers) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#e5e7eb'
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        color: '#9ca3af',
                        stepSize: 1
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
                        color: '#9ca3af'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    ticks: {
                        color: '#9ca3af'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
});
</script>
