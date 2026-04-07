<?php

use yii\base\BaseObject;
use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;
use yii\helpers\Html;
use common\models\user\User;
use common\models\user\UserPayoutSkins;
use common\models\profit\Profit;
use common\models\user\UserBalance;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Отчет по скиндропсам");

// Кэшируем данные на 10 минут
$cacheKey = 'skindrops_report_data_v3';
$data = Yii::$app->cache->get($cacheKey);

if ($data === false) {
    set_time_limit(300); // Увеличиваем лимит времени
    
    // Получаем все данные из БД с оптимизацией
    $query = UserPayoutSkins::find()
        ->with('user')
        ->orderBy(['created_at' => SORT_DESC]);
    
    $allItems = $query->asArray()->all();
    
    // Получаем переводы с баланса скинов в баланс магазина
    $personalBalanceIds = UserBalance::find()
        ->select('id')
        ->where(['type' => UserBalance::TYPE_PERSONAL])
        ->column();
    
    $transfers = Profit::find()
        ->where(['type' => Profit::TYPE_TRANSFER_SKINS])
        ->andWhere(['IN', 'user_balance_id', $personalBalanceIds])
        ->andWhere(['status' => 1])
        ->with(['userBalance.user'])
        ->orderBy(['created_at' => SORT_DESC])
        ->asArray()
        ->all();
    
    // Подготовка данных для статистики
    $itemsByDate = [];
    $statusCounts = [
        'sent' => 0,      // STATUS_WAIT + STATUS_NEW
        'received' => 0,  // STATUS_SUCCESS
        'timeout' => 0,   // STATUS_REJECT
        'new' => 0,       // STATUS_NEW
    ];
    $totalAmount = 0;
    $receivedAmount = 0;
    $timeoutAmount = 0;
    $sentAmount = 0;
    $topUsers = [];
    $topItems = [];
    $byType = ['rust' => 0, 'cs2' => 0];
    $byTypeAmount = ['rust' => 0, 'cs2' => 0];
    
    // Статистика по переводам
    $transfersByDate = [];
    $totalTransfers = 0;
    $transfersCount = count($transfers);
    $topTransferUsers = [];
    
    foreach ($transfers as $transfer) {
        $date = date('Y-m-d', strtotime($transfer['created_at']));
        $amount = (float)$transfer['amount'];
        $totalTransfers += $amount;
        
        // Группировка переводов по датам
        if (!isset($transfersByDate[$date])) {
            $transfersByDate[$date] = [
                'count' => 0,
                'amount' => 0,
            ];
        }
        $transfersByDate[$date]['count']++;
        $transfersByDate[$date]['amount'] += $amount;
        
        // Топ пользователей по переводам
        if (!empty($transfer['userBalance']['user_id'])) {
            $userId = $transfer['userBalance']['user_id'];
            if (!isset($topTransferUsers[$userId])) {
                $topTransferUsers[$userId] = [
                    'user_id' => $userId,
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $topTransferUsers[$userId]['count']++;
            $topTransferUsers[$userId]['amount'] += $amount;
        }
    }
    
    // Загружаем данные пользователей для топа переводов
    $transferUserIds = array_keys($topTransferUsers);
    $transferUsers = User::find()->where(['id' => $transferUserIds])->indexBy('id')->all();
    foreach ($topTransferUsers as $userId => &$userData) {
        if (isset($transferUsers[$userId])) {
            $userData['user'] = $transferUsers[$userId];
        }
    }
    unset($userData);
    
    // Сортировка топа переводов
    usort($topTransferUsers, function($a, $b) {
        return $b['amount'] - $a['amount'];
    });
    $topTransferUsers = array_slice($topTransferUsers, 0, 10);
    
    foreach ($allItems as $item) {
        $date = date('Y-m-d', strtotime($item['created_at']));
        $amount = (float)$item['amount'];
        $type = $item['type'] ?? 'rust';
        
        // Статистика по статусам
        if ($item['status'] == UserPayoutSkins::STATUS_SUCCESS) {
            $statusCounts['received']++;
            $receivedAmount += $amount;
        } elseif ($item['status'] == UserPayoutSkins::STATUS_REJECT) {
            $statusCounts['timeout']++;
            $timeoutAmount += $amount;
        } elseif ($item['status'] == UserPayoutSkins::STATUS_NEW) {
            $statusCounts['new']++;
            $statusCounts['sent']++;
            $sentAmount += $amount;
        } elseif ($item['status'] == UserPayoutSkins::STATUS_WAIT) {
            $statusCounts['sent']++;
            $sentAmount += $amount;
        }
        
        $totalAmount += $amount;
        
        // Группировка по датам
        if (!isset($itemsByDate[$date])) {
            $itemsByDate[$date] = [
                'total' => 0,
                'received' => 0,
                'timeout' => 0,
                'sent' => 0,
                'new' => 0,
                'count' => 0,
                'count_received' => 0,
                'count_timeout' => 0,
                'count_sent' => 0,
            ];
        }
        $itemsByDate[$date]['count']++;
        $itemsByDate[$date]['total'] += $amount;
        
        if ($item['status'] == UserPayoutSkins::STATUS_SUCCESS) {
            $itemsByDate[$date]['received'] += $amount;
            $itemsByDate[$date]['count_received']++;
        } elseif ($item['status'] == UserPayoutSkins::STATUS_REJECT) {
            $itemsByDate[$date]['timeout'] += $amount;
            $itemsByDate[$date]['count_timeout']++;
        } elseif ($item['status'] == UserPayoutSkins::STATUS_WAIT || $item['status'] == UserPayoutSkins::STATUS_NEW) {
            $itemsByDate[$date]['sent'] += $amount;
            $itemsByDate[$date]['count_sent']++;
            if ($item['status'] == UserPayoutSkins::STATUS_NEW) {
                $itemsByDate[$date]['new']++;
            }
        }
        
        // Топ пользователей (только успешные)
        if ($item['status'] == UserPayoutSkins::STATUS_SUCCESS && !empty($item['user_id'])) {
            if (!isset($topUsers[$item['user_id']])) {
                $topUsers[$item['user_id']] = [
                    'user_id' => $item['user_id'],
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $topUsers[$item['user_id']]['count']++;
            $topUsers[$item['user_id']]['amount'] += $amount;
        }
        
        // Топ предметов (только успешные)
        if ($item['status'] == UserPayoutSkins::STATUS_SUCCESS) {
            $itemName = $item['name'] ?? 'Неизвестно';
            if (!isset($topItems[$itemName])) {
                $topItems[$itemName] = [
                    'name' => $itemName,
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            $topItems[$itemName]['count']++;
            $topItems[$itemName]['amount'] += $amount;
        }
        
        // Статистика по типам
        if (isset($byType[$type])) {
            $byType[$type]++;
            if ($item['status'] == UserPayoutSkins::STATUS_SUCCESS) {
                $byTypeAmount[$type] += $amount;
            }
        }
    }
    
    // Загружаем данные пользователей для топа
    $userIds = array_keys($topUsers);
    $users = User::find()->where(['id' => $userIds])->indexBy('id')->all();
    foreach ($topUsers as $userId => &$userData) {
        if (isset($users[$userId])) {
            $userData['user'] = $users[$userId];
        }
    }
    unset($userData);
    
    // Сортировка топов
    usort($topUsers, function($a, $b) {
        return $b['amount'] - $a['amount'];
    });
    $topUsers = array_slice($topUsers, 0, 10);
    
    usort($topItems, function($a, $b) {
        return $b['amount'] - $a['amount'];
    });
    $topItems = array_slice($topItems, 0, 10);
    
    // Сортировка дат
    krsort($itemsByDate);
    $last30Days = array_slice($itemsByDate, 0, 30, true);
    
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
        'allItems' => $allItems,
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
        'transfers' => $transfers,
        'transfersByDate' => $transfersByDate,
        'totalTransfers' => $totalTransfers,
        'transfersCount' => $transfersCount,
        'topTransferUsers' => $topTransferUsers,
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

$totalItems = count($allItems);
$successRate = $totalItems > 0 ? ($statusCounts['received'] / $totalItems) * 100 : 0;
$avgItemPrice = $statusCounts['received'] > 0 ? $receivedAmount / $statusCounts['received'] : 0;
$efficiency = $totalAmount > 0 ? ($receivedAmount / $totalAmount) * 100 : 0;
$lossRate = $totalAmount > 0 ? ($timeoutAmount / $totalAmount) * 100 : 0;

$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', ['position' => \yii\web\View::POS_HEAD]);
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
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
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Средняя цена предмета</div>
                    <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                        <?= number_format($avgItemPrice, 2, '.', ' ') ?> руб.
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Эффективность</div>
                    <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                        <?= number_format($efficiency, 1, '.', ' ') ?>%
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Потери</div>
                    <div class="ds-text--danger" style="font-size: 1.5rem; font-weight: 600;">
                        <?= number_format($lossRate, 1, '.', ' ') ?>%
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">В процессе</div>
                    <div class="ds-text--info" style="font-size: 1.5rem; font-weight: 600;">
                        <?= number_format($sentAmount, 2, '.', ' ') ?> руб.
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Переводов в магазин</div>
                    <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                        <?= $transfersCount ?> операций
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Сумма переводов</div>
                    <div class="ds-text--success" style="font-size: 1.5rem; font-weight: 600;">
                        <?= number_format($totalTransfers, 2, '.', ' ') ?> руб.
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Средний перевод</div>
                    <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                        <?= $transfersCount > 0 ? number_format($totalTransfers / $transfersCount, 2, '.', ' ') : 0 ?> руб.
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">Rust</div>
                    <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
                        <?= $byType['rust'] ?> операций (<?= number_format($byTypeAmount['rust'], 2, '.', ' ') ?> руб.)
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ds-card" style="background: hsl(0 0% 11.8% / 1); padding: 1rem;">
                    <div class="ds-text--secondary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">CS2</div>
                    <div class="ds-text--primary" style="font-size: 1.5rem; font-weight: 600;">
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
                    <h5 class="ds-card__header-title">Динамика по дням (последние 30 дней)</h5>
                </div>
                <div class="ds-card__body">
                    <div style="position: relative; height: 300px;">
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
                    <h5 class="ds-card__header-title">Распределение по статусам</h5>
                </div>
                <div class="ds-card__body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h5 class="ds-card__header-title">Количество операций по дням</h5>
                </div>
                <div class="ds-card__body">
                    <div style="position: relative; height: 300px;">
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
                    <h5 class="ds-card__header-title">Распределение по типам игр</h5>
                </div>
                <div class="ds-card__body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h5 class="ds-card__header-title">Переводы в магазин по дням</h5>
                </div>
                <div class="ds-card__body">
                    <div style="position: relative; height: 300px;">
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
                    <h5 class="ds-card__header-title">Топ-10 пользователей (вывод скинов)</h5>
                </div>
                <div class="ds-card__body">
                    <div class="table-responsive">
                        <table class="table">
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
                    <h5 class="ds-card__header-title">Топ-10 предметов</h5>
                </div>
                <div class="ds-card__body">
                    <div class="table-responsive">
                        <table class="table">
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
                    <h5 class="ds-card__header-title">Топ-10 пользователей по переводам в магазин</h5>
                </div>
                <div class="ds-card__body">
                    <div class="table-responsive">
                        <table class="table">
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
            <h5 class="ds-card__header-title">Детальная статистика по датам (последние 30 дней)</h5>
        </div>
        <div class="ds-card__body">
            <div class="table-responsive">
                <table class="table">
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
