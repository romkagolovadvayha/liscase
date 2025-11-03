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

<article id="<?= (int)$model->id ?>" tabindex="<?= (int)$index ?>" class="blog-card">
    <?php if (!empty($model->blogImages[0])): ?>
        <div class="blog-card_cover">
            <a href="<?= $model->getUrl() ?>" class="blog-card_cover_link">
                <img loading="lazy"
                     src="<?= $model->blogImages[0]->getPublicUrl() ?>"
                     alt="<?= Html::encode($model->name) ?>">
                <div class="blog-card_cover_overlay">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
        </div>
    <?php endif; ?>
    
    <div class="blog-card_content">
        <div class="blog-card_header">
            <div class="blog-card_meta">
                <div class="blog-card_date">
                    <i class="far fa-calendar-alt"></i>
                    <time datetime="<?= Html::encode($date->format('c')) ?>">
                        <?= Html::encode($date->format('d.m.Y')) ?>
                    </time>
                </div>
                
                <?php if ($bc): ?>
                    <div class="blog-card_category">
                        <i class="fas fa-tag"></i>
                        <a href="<?= $bc->getUrl() ?>"><?= Yii::t('database', $bc->name) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="<?= $model->getUrl() ?>" class="blog-card_title-link">
            <h2 class="blog-card_title"><?= Yii::t('database', $model->name) ?></h2>
        </a>

        <div class="blog-card_description">
            <?= Yii::t('database', $model->description) ?>
        </div>

        <div class="blog-card_footer">
            <div class="blog-card_stats">
                <div class="blog-card_stat">
                    <i class="far fa-eye"></i>
                    <span><?= number_format($model->views, 0, '.', ' ') ?></span>
                </div>
                
                <div class="blog-card_stat">
                    <i class="far fa-comment"></i>
                    <span><?= count($model->comments) ?></span>
                </div>
            </div>
            
            <a href="<?= $model->getUrl() ?>" class="blog-card_read-more">
                <?= Yii::t('common', 'Читать') ?>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</article>
