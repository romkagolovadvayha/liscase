<?php

use common\models\user\UserDrop;
use common\models\servers\Servers;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/** @var $dataProvider */
/** @var $searchModel \common\models\user\UserDropSearch */

$this->title = Yii::t('common', 'Предметы пользователей');

// Batch loading связей для оптимизации
$models = $dataProvider->getModels();
$userIds = [];
$dropIds = [];

foreach ($models as $model) {
    if ($model->user_id) {
        $userIds[] = $model->user_id;
    }
    if ($model->drop_id) {
        $dropIds[] = $model->drop_id;
    }
}

// Загружаем всех пользователей одним запросом
$users = [];
if (!empty($userIds)) {
    $users = \common\models\user\User::find()
        ->where(['id' => array_unique($userIds)])
        ->indexBy('id')
        ->all();
}

// Загружаем все серверы одним запросом
$serverIds = [];
foreach ($users as $user) {
    if ($user->server_id) {
        $serverIds[] = $user->server_id;
    }
}
$servers = [];
if (!empty($serverIds)) {
    $servers = \common\models\servers\Servers::find()
        ->where(['id' => array_unique($serverIds)])
        ->indexBy('id')
        ->all();
}

// Загружаем все предметы одним запросом
$drops = [];
if (!empty($dropIds)) {
    $drops = \common\models\box\Drop::find()
        ->where(['id' => array_unique($dropIds)])
        ->indexBy('id')
        ->all();
}

// Создаем массивы для быстрого доступа в view
$usersMap = [];
$serversMap = [];
$dropsMap = [];

foreach ($models as $model) {
    if ($model->user_id && isset($users[$model->user_id])) {
        $usersMap[$model->id] = $users[$model->user_id];
        if ($users[$model->user_id]->server_id && isset($servers[$users[$model->user_id]->server_id])) {
            $serversMap[$model->id] = $servers[$users[$model->user_id]->server_id];
        }
    }
    if ($model->drop_id && isset($drops[$model->drop_id])) {
        $dropsMap[$model->id] = $drops[$model->drop_id];
    }
}
?>
<div class="user-drop-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?php
            $serversList = ArrayHelper::map(
                Servers::find()->orderBy(['name' => SORT_ASC])->all(),
                'id',
                'name'
            );
            ?>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel'  => $searchModel,
                'columns'      => [
                    [
                        'class' => 'yii\grid\CheckboxColumn',
                        'options' => ['width' => '30'],
                    ],
                    [
                        'attribute' => 'id',
                        'format'    => 'raw',
                        'options'   => ['width' => '70'],
                    ],
                    [
                        'attribute' => 'user_username',
                        'label' => Yii::t('common', 'Пользователь'),
                        'format' => 'raw',
                        'value' => function (UserDrop $model) use ($usersMap) {
                            // Используем предзагруженного пользователя
                            $user = $usersMap[$model->id] ?? null;
                            
                            if (!$user && $model->user_id) {
                                $user = \common\models\user\User::findOne($model->user_id);
                            }

                            if (!$user) {
                                return $model->user_id ? 'ID: ' . $model->user_id : null;
                            }

                            return Html::a(
                                Html::encode($user->username),
                                ['/user/profile', 'userId' => $model->user_id],
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'attribute' => 'server_id',
                        'label' => Yii::t('common', 'Сервер'),
                        'filterType' => GridView::FILTER_SELECT2,
                        'filter' => ArrayHelper::merge(['' => 'Все'], $serversList),
                        'options' => ['width' => '150'],
                        'value' => function (UserDrop $model) use ($serversMap) {
                            // Используем предзагруженный сервер
                            $server = $serversMap[$model->id] ?? null;

                            if (!$server) {
                                return null;
                            }

                            return Html::encode($server->name);
                        },
                    ],
                    [
                        'attribute' => 'drop_name',
                        'label' => Yii::t('common', 'Предмет'),
                        'format' => 'raw',
                        'value' => function (UserDrop $model) use ($dropsMap) {
                            // Используем предзагруженный drop
                            $drop = $dropsMap[$model->id] ?? null;

                            if (!$drop && $model->drop_id) {
                                $drop = \common\models\box\Drop::findOne($model->drop_id);
                            }

                            if (!$drop) {
                                return $model->drop_id ? 'ID: ' . $model->drop_id : null;
                            }

                            $image = '';
                            if ($drop->imageOrig) {
                                $image = Html::img($drop->imageOrig->getImagePubUrl(false), [
                                    'width' => '32px',
                                    'height' => '32px',
                                    'style' => 'border-radius: 4px; object-fit: cover; margin-right: 8px;',
                                    'alt' => Html::encode($drop->name ?? ''),
                                ]);
                            }
                            return $image . Html::encode($drop->name);
                        },
                    ],
                    [
                        'attribute' => 'count',
                        'label' => Yii::t('common', 'Количество'),
                        'options' => ['width' => '100'],
                    ],
                    [
                        'attribute' => 'status',
                        'filterType' => GridView::FILTER_SELECT2,
                        'filter' => ArrayHelper::merge(['' => 'Все'], UserDrop::getStatusList()),
                        'options' => ['width' => '150'],
                        'format' => 'raw',
                        'value' => function (UserDrop $model) {
                            $statusList = UserDrop::getStatusList();
                            $status = ArrayHelper::getValue($statusList, $model->status);
                            $badgeClasses = [
                                UserDrop::STATUS_TEMP_BLOCKED => 'ds-badge--danger',
                                UserDrop::STATUS_ACTIVE => 'ds-badge--success',
                                UserDrop::STATUS_SENDED => 'ds-badge--info',
                                UserDrop::STATUS_SELL => 'ds-badge--warning',
                                UserDrop::STATUS_WAIT => 'ds-badge--primary',
                            ];
                            $badgeClass = $badgeClasses[$model->status] ?? 'ds-badge--secondary';
                            return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    [
                        'attribute' => 'sended_at',
                        'label' => Yii::t('common', 'Дата отправки'),
                        'options' => ['width' => '180'],
                        'class' => \common\components\grid\DateColumn::class,
                    ],
                    [
                        'attribute' => 'created_at',
                        'label' => Yii::t('common', 'Дата создания'),
                        'options' => ['width' => '180'],
                        'class' => \common\components\grid\DateColumn::class,
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{update-status}',
                        'options' => ['width' => '100'],
                        'buttons' => [
                            'update-status' => function ($url, $model) {
                                $statusList = UserDrop::getStatusList();
                                $items = [];
                                foreach ($statusList as $statusId => $statusName) {
                                    if ($statusId != $model->status) {
                                        $items[] = [
                                            'label' => $statusName,
                                            'url' => Url::to(['update-status', 'id' => $model->id]),
                                            'linkOptions' => [
                                                'data-method' => 'post',
                                                'data-params' => ['status' => $statusId],
                                                'class' => 'change-status-link',
                                            ],
                                        ];
                                    }
                                }
                                if (empty($items)) {
                                    return null;
                                }
                                return Html::dropDownList(
                                    'status_' . $model->id,
                                    $model->status,
                                    $statusList,
                                    [
                                        'class' => 'form-control status-select',
                                        'data-id' => $model->id,
                                        'style' => 'width: 120px;',
                                    ]
                                );
                            },
                        ],
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>

<?php
$bulkUpdateUrl = Url::to(['bulk-update-status']);
$updateStatusUrl = Url::to(['update-status']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$js = <<<JS
// Изменение статуса для одного элемента
$(document).on('change', '.status-select', function() {
    var select = $(this);
    var id = select.data('id');
    var status = select.val();

    if (confirm('Изменить статус?')) {
        var form = $('<form>', {
            'method': 'POST',
            'action': '{$updateStatusUrl}?id=' + id
        });

        form.append($('<input>', {
            'type': 'hidden',
            'name': 'status',
            'value': status
        }));

        form.append($('<input>', {
            'type': 'hidden',
            'name': '{$csrfParam}',
            'value': '{$csrfToken}'
        }));

        $('body').append(form);
        form.submit();
    } else {
        select.val(select.data('prev-value') || select.find('option:first').val());
    }
});

// Сохранение предыдущего значения при фокусе
$(document).on('focus', '.status-select', function() {
    $(this).data('prev-value', $(this).val());
});

// Массовое изменение статуса
function bulkUpdateStatus() {
    var selectedIds = [];
    $('input[name="selection[]"]:checked').each(function() {
        selectedIds.push($(this).val());
    });

    if (selectedIds.length === 0) {
        alert('Выберите элементы для изменения');
        return;
    }

    var statusList = {
        0: 'Временно блокирован',
        1: 'Доступен',
        2: 'Отправлен',
        3: 'Продан',
        4: 'Отправляется'
    };

    var statusSelect = $('<select>', {
        id: 'bulk-status-select',
        style: 'width: 100%; margin: 10px 0; padding: 5px;',
        html: '<option value="">Выберите статус</option>'
    });

    for (var key in statusList) {
        statusSelect.append($('<option>', {
            value: key,
            text: statusList[key]
        }));
    }

    var dialog = $('<div>', {
        title: 'Изменение статуса',
        html: '<p>Выберите новый статус для ' + selectedIds.length + ' элементов:</p>'
    }).append(statusSelect);

    if (confirm('Изменить статус для ' + selectedIds.length + ' элементов?')) {
        var status = prompt('Введите номер статуса (0-4):\\n0 - Временно блокирован\\n1 - Доступен\\n2 - Отправлен\\n3 - Продан\\n4 - Отправляется');

        if (status === null || status === '') {
            return;
        }

        if (!statusList[status]) {
            alert('Неверный статус');
            return;
        }

        var form = $('<form>', {
            'method': 'POST',
            'action': '{$bulkUpdateUrl}'
        });

        selectedIds.forEach(function(id) {
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'ids[]',
                'value': id
            }));
        });

        form.append($('<input>', {
            'type': 'hidden',
            'name': 'status',
            'value': status
        }));

        form.append($('<input>', {
            'type': 'hidden',
            'name': '{$csrfParam}',
            'value': '{$csrfToken}'
        }));

        $('body').append(form);
        form.submit();
    }
}

// Добавляем кнопку массового изменения статуса
if ($('.grid-view').length) {
    $('.grid-view').before('<div style="margin-bottom: 10px;"><button type="button" class="ds-btn ds-btn--primary" onclick="bulkUpdateStatus()">Изменить статус выбранных</button></div>');
}
JS;

$this->registerJs($js);
?>

