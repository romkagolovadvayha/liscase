<?php
use yii\helpers\Html;

/** @var common\models\blog\Blog $model */
/** @var yii\web\View $this */
/** @var integer $index */

$date = new DateTime($model->created_at);

// рейтинг, если нужен
$rating = 0;
foreach ($model->blogRatings as $_rating) {
    $rating += $_rating->weight;
}

// осторожно с родителем (может быть null)
$bc = $model->blogCategory;
$pc = $bc ? $bc->parentCategory ?? ($bc->parent ?? null) : null; // если ты уже сделал связь parent()
?>

<article id="<?= (int)$model->id ?>" tabindex="<?= (int)$index ?>" class="blog_item masonry-card">
    <?php if (!empty($model->blogImages[0])): ?>
        <div class="blog_item_body_cover ratio-thumb">
            <a href="<?= $model->getUrl() ?>">
                <!-- можно lazy -->
                <img loading="lazy"
                     src="<?= $model->blogImages[0]->getPublicUrl() ?>"
                     alt="<?= Html::encode($model->name) ?>">
            </a>
        </div>
    <?php endif; ?>
    <div class="blog_item_container_wrap">
        <div class="blog_item_container">
            <div class="blog_item_snippet_meta">
                <span class="blog_item_snippet_meta_author">
                    <span class="blog_item_snippet_meta_author_user">
                        <span class="blog_item_snippet_meta_author_user_published">
                            <!-- FIX: был лишний символ "<" в datetime -->
                            <time datetime="<?= Html::encode($date->format('c')) ?>" title="<?= Html::encode($date->format('d.m.Y, H:i')) ?>">
                                <?= Html::encode($date->format('d.m.Y, H:i')) ?>
                            </time>
                        </span>
                    </span>
                </span>
            </div>
        </div>

        <a href="<?= $model->getUrl() ?>">
            <h2 class="blog_item_title"><?= Yii::t('database', $model->name) ?></h2>
        </a>

        <div class="blog_item_categories">
            <?php if ($pc): ?>
                <a href="<?= $pc->getUrl() ?>"><?= Yii::t('database', $pc->name) ?></a>,
            <?php endif; ?>
            <?php if ($bc): ?>
                <a href="<?= $bc->getUrl() ?>"><?= Yii::t('database', $bc->name) ?></a>
            <?php endif; ?>
        </div>

        <div class="blog_item_body p2 mb-24">
            <div class="blog_item_body_text mb-24">
                <?= Yii::t('database', $model->description) ?>
            </div>
        </div>

        <div class="blog_item_data">
            <div class="blog_item_data_info">
                <div class="blog_item_data_item blog_item_data_views">
                    <div class="blog_item_data_item_icon_wrapper" title="<?= Yii::t('common', 'Количество просмотров') ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                            <path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/>
                        </svg>
                        <span><?= (int)$model->views ?></span>
                    </div>
                </div>

                <div class="blog_item_data_item blog_item_data_comments">
                    <a href="<?= $model->getUrl() ?>#comments" class="blog_item_data_item_icon_wrapper" title="<?= Yii::t('common', 'Количество комментариев') ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                            <path d="M19.619 21.671c-5.038 1.227-8.711-1.861-8.711-5.167 0-3.175 3.11-5.467 6.546-5.467 3.457 0 6.546 2.309 6.546 5.467 0 1.12-.403 2.22-1.117 3.073-.029 1 .558 2.435 1.088 3.479-1.419-.257-3.438-.824-4.352-1.385zm-10.711-5.167c0-4.117 3.834-7.467 8.546-7.467.886 0 1.74.119 2.544.338-.021-4.834-4.761-8.319-9.998-8.319-5.281 0-10 3.527-10 8.352 0 1.71.615 3.391 1.705 4.695.047 1.527-.851 3.718-1.661 5.313 2.168-.391 5.252-1.258 6.649-2.115.803.196 1.576.304 2.328.363-.067-.379-.113-.765-.113-1.16z"/>
                        </svg>
                        <span><?= count($model->comments) ?></span>
                    </a>
                </div>
            </div>
            <a href="<?= $model->getUrl() ?>" class="button button-secondary button-size__s h-36">
                <span class="button__text"><?= Yii::t('common', 'Читать далее') ?></span>
            </a>
        </div>
    </div>
</article>
