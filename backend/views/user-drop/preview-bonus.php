<?php

use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = Yii::t('common', 'Предпросмотр начисления бонуса');
?>

<div class="user-drop-preview-bonus-page">
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
                Сумма бонуса: <strong><?= Html::encode($amount) ?> руб.</strong><br>
                Комментарий: <strong><?= Html::encode($comment) ?></strong><br>
                Найдено пользователей: <strong><?= $count ?></strong><br>
                Общая сумма: <strong><?= number_format($totalAmount, 2, '.', ' ') ?> руб.</strong>
            </div>

            <?php if ($count > 0): ?>
                <p class="text-warning">
                    <strong>Внимание!</strong> Будет начислен бонус <?= Html::encode($amount) ?> руб. каждому из <?= $count ?> пользователей.
                    Общая сумма начисления: <strong><?= number_format($totalAmount, 2, '.', ' ') ?> руб.</strong>
                </p>

                <?= GridView::widget([
                    'dataProvider' => new \yii\data\ArrayDataProvider([
                        'allModels' => $users,
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
                            'value' => function ($user) {
                                return Html::a(
                                    Html::encode($user->username),
                                    ['/user/profile', 'userId' => $user->id],
                                    ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                );
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Steam ID'),
                            'value' => function ($user) {
                                return Html::encode($user->steam_id);
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Сервер'),
                            'value' => function ($user) {
                                if (empty($user->server)) {
                                    return null;
                                }
                                return Html::encode($user->server->name);
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Текущий баланс'),
                            'format' => 'raw',
                            'value' => function ($user) {
                                $balance = $user->getPersonalBalance();
                                return $balance ? number_format($balance->balanceCeil, 2, '.', ' ') . ' руб.' : '0 руб.';
                            },
                        ],
                        [
                            'label' => Yii::t('common', 'Бонус'),
                            'format' => 'raw',
                            'value' => function () use ($amount) {
                                return Html::tag('span', '+' . number_format($amount, 2, '.', ' ') . ' руб.', [
                                    'class' => 'ds-badge ds-badge--success',
                                ]);
                            },
                        ],
                    ],
                ]); ?>

                <div class="form-group" style="margin-top: 20px;">
                    <?php $form = \yii\widgets\ActiveForm::begin([
                        'method' => 'post',
                        'action' => ['confirm-bonus'],
                    ]); ?>

                    <?= Html::hiddenInput('server_id', $server->id) ?>
                    <?= Html::hiddenInput('amount', $amount) ?>
                    <?= Html::hiddenInput('comment', $comment) ?>

                    <?= Html::submitButton('Подтвердить и начислить', [
                        'class' => 'ds-btn ds-btn--success',
                        'onclick' => 'return confirm("Вы уверены, что хотите начислить бонус ' . $amount . ' руб. для ' . $count . ' пользователей?\\nОбщая сумма: ' . number_format($totalAmount, 2, '.', ' ') . ' руб.");'
                    ]) ?>
                    <?= Html::a('Отмена', ['bonus-by-server'], ['class' => 'ds-btn ds-btn--secondary']) ?>

                    <?php \yii\widgets\ActiveForm::end(); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    Не найдено пользователей, игравших на этом сервере в текущем wipe.
                </div>
                <div class="form-group">
                    <?= Html::a('Вернуться', ['bonus-by-server'], ['class' => 'ds-btn ds-btn--secondary']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

