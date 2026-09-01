<?php

use common\models\skindrops\SkindropsLink;
use yii\base\BaseObject;
use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;
use common\models\user\User;
use yii\helpers\Html;
use common\models\user\UserPayoutSkins;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Скиндропс");

// Кэшируем данные на 5 минут
$cacheKey = 'skindrops_index_data_v2';
$data = Yii::$app->cache->get($cacheKey);

if ($data === false) {
    // Получаем данные из БД с оптимизацией
    $query = UserPayoutSkins::find()
        ->with('user')
        ->orderBy(['created_at' => SORT_DESC])
        ->limit(1000); // Ограничиваем для производительности
    
    $list = $query->asArray()->all();
    
    $data = [
        'list' => $list,
        'total' => UserPayoutSkins::find()->count(),
    ];
    
    Yii::$app->cache->set($cacheKey, $data, 300); // 5 минут
}

$list = $data['list'];
$totalItems = $data['total'];

$dataProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $list,
    'totalCount' => count($list),
    'pagination' => [
        'pageSize' => 20,
    ],
]);

// Статистика
$sentCount = count(array_filter($list, function($item) { return $item['status'] == UserPayoutSkins::STATUS_WAIT || $item['status'] == UserPayoutSkins::STATUS_NEW; }));
$receivedCount = count(array_filter($list, function($item) { return $item['status'] == UserPayoutSkins::STATUS_SUCCESS; }));
$timeoutCount = count(array_filter($list, function($item) { return $item['status'] == UserPayoutSkins::STATUS_REJECT; }));
$totalAmount = array_sum(array_column($list, 'amount'));
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= Alert::widget(); ?>

    <!-- Статистика -->
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
                    <div class="ds-counter__value"><?= $receivedCount ?></div>
                    <div class="ds-counter__label">Получено</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value <?= $timeoutCount > 0 ? 'ds-text--danger' : '' ?>"><?= $timeoutCount ?></div>
                    <div class="ds-counter__label">Не получено</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= $sentCount ?></div>
                    <div class="ds-counter__label">В процессе</div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="ds-counter">
                    <div class="ds-counter__value"><?= number_format($totalAmount, 2, '.', ' ') ?> RUB</div>
                    <div class="ds-counter__label">Общая сумма</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица операций -->
    <div class="ds-card">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">История операций</h5>
        </div>
        <div class="ds-card__body">
            <?= \kartik\grid\GridView::widget([
                'dataProvider' => $dataProvider,
                'tableOptions' => [
                    'class' => 'kv-grid-table table table-bordered table-striped kv-table-wrap',
                    'aria-label' => 'История операций со скинами',
                ],
                'layout'       => "{items} {pager}",
                'columns'      => [
                    [
                        'attribute' => 'name',
                        'label'     => Yii::t('common', "Название"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            if (!empty($model['image300'])) {
                                return Html::img($model['image300'], [
                                    'alt' => Html::encode($model['name'] ?? ''),
                                    'style' => 'width: 64px; height: 64px; object-fit: contain; margin-right: 10px; vertical-align: middle;',
                                    'loading' => 'lazy',
                                ]) . ' ' . Html::encode($model['name'] ?? 'Неизвестно');
                            }
                            return Html::encode($model['name'] ?? 'Неизвестно');
                        },
                    ],
                    [
                        'attribute' => 'status',
                        'options'   => ['width' => '150'],
                        'label'     => Yii::t('common', "Статус"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            $statusList = UserPayoutSkins::getStatusList();
                            $status = $statusList[$model['status']] ?? 'Неизвестно';
                            $badgeClass = 'ds-badge--info';
                            
                            if ($model['status'] == UserPayoutSkins::STATUS_SUCCESS) {
                                $badgeClass = 'ds-badge--success';
                            } elseif ($model['status'] == UserPayoutSkins::STATUS_REJECT) {
                                $badgeClass = 'ds-badge--danger';
                            } elseif ($model['status'] == UserPayoutSkins::STATUS_NEW) {
                                $badgeClass = 'ds-badge--warning';
                            }
                            
                            return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    [
                        'attribute' => 'amount',
                        'options'   => ['width' => '150'],
                        'label'     => Yii::t('common', "Сумма"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            return number_format($model['amount'], 2, '.', ' ') . " RUB";
                        },
                    ],
                    [
                        'attribute' => 'type',
                        'options'   => ['width' => '100'],
                        'label'     => Yii::t('common', "Тип"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            $type = $model['type'] ?? 'rust';
                            $badgeClass = $type == 'rust' ? 'ds-badge--warning' : 'ds-badge--info';
                            return Html::tag('span', strtoupper($type), ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'options'   => ['width' => '150'],
                        'label'     => Yii::t('common', "Дата"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            return date("Y-m-d H:i:s", strtotime($model['created_at']));
                        },
                    ],
                    [
                        'attribute' => 'username',
                        'options'   => ['width' => '150'],
                        'label'     => Yii::t('common', "Пользователь"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            if (empty($model['user_id'])) {
                                return Html::tag('span', '—', ['class' => 'ds-text--secondary']);
                            }
                            
                            $user = User::findOne($model['user_id']);
                            if (empty($user)) {
                                return Html::tag('span', '—', ['class' => 'ds-text--secondary']);
                            }
                            
                            return Html::a(
                                Html::encode($user->username),
                                '/profile/' . $user->id,
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'attribute' => 'steamId',
                        'options'   => ['width' => '150'],
                        'label'     => Yii::t('common', "Steam ID"),
                        'format'    => 'raw',
                        'value'     => function ($model) {
                            if (empty($model['user_id'])) {
                                return Html::tag('span', '—', ['class' => 'ds-text--secondary']);
                            }
                            
                            $user = User::findOne($model['user_id']);
                            if (empty($user)) {
                                return Html::tag('span', '—', ['class' => 'ds-text--secondary']);
                            }
                            
                            return Html::a(
                                $user->steam_id,
                                'https://steamcommunity.com/profiles/' . $user->steam_id,
                                ['target' => '_blank', 'class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
