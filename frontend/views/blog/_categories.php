<?php
/** @var \yii\web\View $this */
/** @var \common\models\blog\BlogCategory[] $categories */
?>
<nav class="blog-categories" aria-label="<?= Yii::t('common','Категории блога') ?>">
    <div class="blog-categories_title">
        <i class="fas fa-filter"></i>
        <span><?= Yii::t('common', 'Категории') ?></span>
    </div>
    
    <ul class="blog-categories_list">
        <?php foreach ($categories as $cat): ?>
            <li class="blog-categories_item">
                <a class="blog-categories_link" href="<?= $cat->getUrl() ?>" aria-haspopup="<?= !empty($cat->children) ? 'true' : 'false' ?>" aria-expanded="false">
                    <i class="fas fa-bookmark"></i>
                    <span class="blog-categories_name"><?= Yii::t('database', $cat->name) ?></span>
                    <?php if (!empty($cat->children)): ?>
                        <i class="fas fa-chevron-down blog-categories_arrow"></i>
                    <?php endif; ?>
                </a>

                <?php if (!empty($cat->children)): ?>
                    <div class="blog-categories_dropdown">
                        <ul class="blog-categories_dropdown_list">
                            <?php foreach ($cat->children as $sub): ?>
                                <li class="blog-categories_dropdown_item">
                                    <a class="blog-categories_dropdown_link" href="<?= $sub->getUrl() ?>">
                                        <i class="fas fa-angle-right"></i>
                                        <?= Yii::t('database', $sub->name) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
