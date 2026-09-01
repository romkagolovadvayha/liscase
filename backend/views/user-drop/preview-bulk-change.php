<?php

use common\models\user\UserDrop;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = Yii::t('common', 'Предпросмотр изменений');
?>

<div class="user-drop-preview-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <div class="alert alert-info">
                <strong>Параметры:</strong><br>
                Сервер: <strong><?= Html::encode($server->name) ?></strong><br>
                Wipe сервера: <strong><?= Html::encode($server->currentWipe()) ?></strong><br>
                Дата начала: <strong><?= Html::encode($dateFrom) ?></strong><br>
                Дата окончания: <strong><?= Html::encode($dateTo) ?></strong><br>
                Найдено записей: <strong><?= $count ?></strong>
            </div>

            <?php if ($count > 0): ?>
                <p class="text-warning">
                    <strong>Внимание!</strong> Будет изменен статус с "Отправлен" (2) на "Доступен" (1) для <?= $count ?> записей.
                </p>

                <?= GridView::widget([
                    'dataProvider' => new \yii\data\ArrayDataProvider([
                        'allModels' => $items,
                        'pagination' => [
                            'pageSize' => 50,
                        ],
                    ]),
                    'columns' => [
                        [
                            'attribute' => 'id',
                            'format' => 'raw',
                            'options' => ['width' => '70'],
                        ],
                        [
                            'label' => Yii::t('common', 'Пользователь'),
                            'format' => 'raw',
                            'value' => function (UserDrop $model) {
                                if (empty($model->user)) {
                                    return $model->user_id ? 'ID: ' . $model->user_id : null;
                                }
                                return Html::a(
                                    Html::encode($model->user->username),
                                    '/profile/' . $model->user_id,
                                    ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                );
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Сервер'),
                            'value' => function (UserDrop $model) {
                                if (empty($model->user) || empty($model->user->server)) {
                                    return null;
                                }
                                return Html::encode($model->user->server->name);
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Предмет'),
                            'format' => 'raw',
                            'value' => function (UserDrop $model) {
                                $drop = $model->dropOne;
                                if (empty($drop)) {
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
                        ],
                        [
                            'attribute' => 'status',
                            'label' => Yii::t('common', 'Текущий статус'),
                            'format' => 'raw',
                            'value' => function (UserDrop $model) {
                                $statusList = UserDrop::getStatusList();
                                $status = ArrayHelper::getValue($statusList, $model->status);
                                return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ds-badge--info']);
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Новый статус'),
                            'format' => 'raw',
                            'value' => function () {
                                $statusList = UserDrop::getStatusList();
                                $status = ArrayHelper::getValue($statusList, UserDrop::STATUS_ACTIVE);
                                return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ds-badge--success']);
                            },
                        ],
                        [
                            'attribute' => 'sended_at',
                            'label' => Yii::t('common', 'Дата отправки'),
                            'options' => ['width' => '180'],
                            'class' => \common\components\grid\DateColumn::class,
                        ],
                    ],
                ]); ?>

                <div class="form-group" style="margin-top: 20px;">
                    <?php $form = \yii\widgets\ActiveForm::begin([
                        'method' => 'post',
                        'action' => ['confirm-bulk-change'],
                    ]); ?>

                    <?= Html::hiddenInput('server_id', $server->id) ?>
                    <?= Html::hiddenInput('date_from', $dateFrom) ?>
                    <?= Html::hiddenInput('date_to', $dateTo) ?>

                    <?= Html::submitButton('Подтвердить и выполнить', [
                        'class' => 'ds-btn ds-btn--success',
                        'onclick' => 'return confirm("Вы уверены, что хотите изменить статус для ' . $count . ' записей?");'
                    ]) ?>
                    <?= Html::a('Отмена', ['bulk-change-by-server'], ['class' => 'ds-btn ds-btn--secondary']) ?>

                    <?php \yii\widgets\ActiveForm::end(); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    Не найдено записей для изменения по указанным параметрам.
                </div>
                <div class="form-group">
                    <?= Html::a('Вернуться', ['bulk-change-by-server'], ['class' => 'ds-btn ds-btn--secondary']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

