<?php
/** @var int $blogId */

use common\models\blog\BlogImage;
use yii\helpers\Html;

$images = BlogImage::find()
                   ->andWhere(['blog_id' => $blogId])
                   ->orderBy(['created_at' => SORT_DESC])
                   ->all();

\yii\web\YiiAsset::register($this);
?>

<?php if (!empty($images)): ?>
    <div class="form-group blog-media-library">
        <label>Загруженные изображения:</label>
        <div class="blog-media-library__grid">
            <?php foreach ($images as $item): ?>
                <div class="blog-media-library__item">
                    <?=$this->render('_file-list-images', [
                        'url' => $item->getPublicUrl(),
                        'name' => $item->link
                    ])?>
                    <div class="blog-media-library__actions">
                        <?= Html::a('Удалить', ['/blog-image/delete', 'id' => $item->id], [
                            'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                            'data' => [
                                'method' => 'post',
                                'confirm' => 'Удалить это изображение?',
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
