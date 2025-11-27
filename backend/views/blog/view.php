<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\blog\Blog;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
$actionGeneratePosts = Yii::$app->cache->get('actionGeneratePosts');
$actionGeneratePost = Yii::$app->cache->get('actionGenerate_Post_' . $model->id);
?>
<div class="blog-view">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ds-flex ds-flex--gap-md">
                <?= Html::a('<i class="bi bi-pencil"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
                <?= Html::a('<i class="bi bi-trash"></i> Удалить', ['delete', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--danger',
                    'data' => [
                        'confirm' => 'Вы уверены, что хотите удалить этот пост?',
                        'method' => 'post',
                    ],
                ]) ?>
                <?php if (empty($actionGeneratePosts) && empty($actionGeneratePost)): ?>
                    <?= Html::a('<i class="bi bi-magic"></i> Генерация контента', ['generate-post', 'postId' => $model->id], [
                        'class' => 'ds-btn ds-btn--info',
                        'data' => [
                            'confirm' => 'Вы уверены, что хотите сгенерировать контент для этого поста?',
                            'method' => 'post',
                        ],
                    ]) ?>
                <?php else: ?>
                    <?= Html::a('<i class="bi bi-hourglass-split"></i> Идет процесс генерации', [''], ['class' => 'ds-btn ds-btn--secondary', 'disabled' => true]) ?>
                <?php endif; ?>
                <?= Html::a('<i class="fab fa-vk"></i> Опубликовать в ВК', ['publish-to-vk', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--warning',
                    'data' => [
                        'confirm' => 'Вы уверены, что хотите опубликовать этот пост в группу ВКонтакте?',
                        'method' => 'post',
                    ],
                ]) ?>
                <?= Html::a('<i class="fab fa-telegram"></i> Опубликовать в Telegram', ['publish-to-telegram', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--info',
                    'data' => [
                        'confirm' => 'Вы уверены, что хотите опубликовать этот пост в Telegram канал?',
                        'method' => 'post',
                    ],
                ]) ?>
            </div>
        </div>
    </div>

    <div class="content">

        <div class="ds-card">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">Информация о посте</h5>
            </div>
            <div class="ds-card__body">
                <?= DetailView::widget([
                    'model' => $model,
                    'attributes' => [
                        'id',
                        'name:ntext',
                        [
                            'attribute' => 'content',
                            'format'    => 'raw',
                        ],
                        'description:ntext',
                        'keywords:ntext',
                        [
                            'attribute' => 'blog_category_id',
                            'format'    => 'raw',
                            'value'     => function (Blog $model) {
                                return Html::a(
                                    $model->blogCategory->name,
                                    ['/blog-category/view', 'id' => $model->blogCategory->id],
                                    ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                );
                            },
                        ],
                        [
                            'attribute' => 'link_name',
                            'label' => 'Ссылка на пост',
                            'format' => 'raw',
                            'value'     => function (Blog $model) {
                                $link = Yii::$app->params['baseUrl'] . $model->getUrl();
                                return Html::a($link, $link, ['target' => '_blank', 'class' => 'ds-text--primary', 'style' => 'text-decoration: none;']);
                            },
                        ],
                        [
                            'attribute' => 'status',
                            'format' => 'raw',
                            'value'     => function (Blog $model) {
                                $status = ArrayHelper::getValue(Blog::getStatusList(), $model->status);
                                $badgeClass = $model->status == Blog::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                                return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                            },
                        ],
                        'created_at',
                    ],
                ]) ?>
            </div>
        </div>
    </div>
</div>
