<?php
/** @var int $blogId */

use common\models\blog\BlogImage;
use yii\helpers\Html;

$images = BlogImage::find()
                   ->andWhere(['blog_id' => $blogId])
                   ->orderBy(['created_at' => SORT_DESC])
                   ->all();
?>

<?php if (!empty($images)): ?>
    <div class="form-group" style="margin-top: 15px;">
        <label>Загруженные изображения:</label>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
            <?php foreach ($images as $item): ?>
                <div style="position: relative; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                    <?=$this->render('_file-list-images', [
                        'url' => $item->getPublicUrl(),
                        'name' => $item->link
                    ])?>
                    <div style="margin-top: 10px; text-align: center;">
                        <a href="/blog-image/delete?id=<?=$item->id?>" 
                           class="btn btn-sm btn-danger" 
                           onclick="return confirm('Вы уверены, что хотите удалить это изображение?');"
                           style="color: white; text-decoration: none;">Удалить</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
