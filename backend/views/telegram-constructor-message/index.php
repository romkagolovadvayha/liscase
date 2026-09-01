<?php

use backend\components\AccessibleKartikGridView as GridView;
use backend\models\TelegramConstructorMessage;
use common\components\helpers\Role;
use common\helpers\HStrings;
use yii\grid\ActionColumn;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var backend\models\TelegramConstructorMessageSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Шаблоны рассылок';
$this->params['contentClass'] = 'content-no-padding';
$totalCount = $dataProvider->getTotalCount();
?>
<div class="mailing-page mailing-templates-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-page-head">
        <div>
            <span class="mailing-page-head__eyebrow">Содержимое</span>
            <h1>Шаблоны</h1>
            <p>Текст, изображение и кнопки сообщения. Один шаблон можно использовать в нескольких рассылках.</p>
        </div>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новый шаблон', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
    </header>

    <div class="mailing-list-head">
        <div><h2>Все шаблоны</h2><span><?= Yii::$app->formatter->asInteger($totalCount) ?> <?= HStrings::pluralForm($totalCount, ['шаблон', 'шаблона', 'шаблонов']) ?></span></div>
        <span class="mailing-list-head__hint"><i class="fa-solid fa-filter" aria-hidden="true"></i> Поиск — в панели фильтров</span>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'layout' => "{items}\n{pager}",
        'filterRowOptions' => ['class' => 'd-none'],
        'options' => ['class' => 'mailing-grid'],
        'tableOptions' => ['class' => 'table mailing-table mailing-template-table'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'emptyText' => 'Шаблонов пока нет.',
        'columns' => [
            [
                'label' => '',
                'format' => 'raw',
                'contentOptions' => ['class' => 'mailing-template-table__image'],
                'value' => static function (TelegramConstructorMessage $model) {
                    $url = $model->getPubUrl();
                    return $url
                        ? Html::img($url, ['loading' => 'lazy', 'alt' => ''])
                        : '<span><i class="fa-regular fa-image" aria-hidden="true"></i></span>';
                },
            ],
            [
                'attribute' => 'title',
                'format' => 'raw',
                'value' => static function (TelegramConstructorMessage $model) {
                    $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags($model->getMessage())));
                    return Html::a(
                        '<strong>' . Html::encode($model->title ?: 'Шаблон #' . $model->id) . '</strong><span>' . Html::encode($excerpt !== '' ? StringHelper::truncate($excerpt, 90) : 'Только изображение') . '</span>',
                        ['view', 'id' => $model->id],
                        ['class' => 'mailing-title-link']
                    );
                },
            ],
            [
                'label' => 'Используется',
                'value' => static fn(TelegramConstructorMessage $model) => $model->getUsageLabel(),
            ],
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y, H:i'],
                'contentOptions' => ['class' => 'mailing-table__date'],
            ],
            [
                'class' => ActionColumn::class,
                'template' => '{view} {update} {delete}',
                'contentOptions' => ['class' => 'mailing-row-actions'],
                'urlCreator' => static fn($action, $model) => Url::toRoute([$action, 'id' => $model->id]),
                'buttons' => [
                    'view' => static fn($url) => Html::a('<i class="fa-solid fa-eye" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'title' => 'Открыть', 'aria-label' => 'Открыть шаблон']),
                    'update' => static fn($url) => Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'title' => 'Редактировать', 'aria-label' => 'Редактировать шаблон']),
                    'delete' => static function ($url, TelegramConstructorMessage $model) {
                        if (!Yii::$app->user->can(Role::ROLE_ADMIN)) {
                            return '';
                        }
                        if ($model->getUsageCount() > 0) {
                            return Html::tag('span', '<i class="fa-solid fa-lock" aria-hidden="true"></i>', [
                                'class' => 'ds-btn ds-btn--icon ds-btn--ghost is-disabled',
                                'title' => 'Шаблон используется и не может быть удалён',
                                'aria-label' => 'Удаление недоступно: шаблон используется',
                                'aria-disabled' => 'true',
                            ]);
                        }

                        return Html::a('<i class="fa-solid fa-trash" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost mailing-danger-action', 'title' => 'Удалить', 'aria-label' => 'Удалить шаблон', 'data' => ['confirm' => 'Удалить шаблон?', 'method' => 'post']]);
                    },
                ],
            ],
        ],
    ]) ?>
</div>
