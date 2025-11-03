<?php

use yii\widgets\ListView;

/** @var string $title */
/** @var integer $categoryId */
/** @var \yii\data\ActiveDataProvider $dataProvider */

?>
<div class="blog-header">
    <div class="blog-header_controls">
        <div class="blog-sort-buttons">
            <?= ListView::widget([
                'dataProvider' => $dataProvider,
                'layout'       => "{sorter}",
                'itemOptions' => ['tag' => false],
                'options' => ['tag' => false],
                'sorter' => [
                    'attributes' => ['views', 'created_at']
                ],
            ]) ?>
        </div>
        
        <a href="/rss<?= !empty($categoryId) ? "?category=$categoryId" : '' ?>" 
           class="blog-rss-button" 
           target="_blank"
           title="<?= Yii::t('common', 'RSS лента') ?>">
            <i class="fas fa-rss"></i>
            <span>RSS</span>
        </a>
    </div>
</div>