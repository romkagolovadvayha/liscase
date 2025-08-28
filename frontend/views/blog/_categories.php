<?php
/** @var \yii\web\View $this */
/** @var \common\models\blog\BlogCategory[] $categories */
?>
<nav class="blog-cats" aria-label="<?= Yii::t('common','Категории блога') ?>">
    <ul class="blog-cats__list">
        <?php foreach ($categories as $cat): ?>
            <li class="blog-cats__item">
                <a class="blog-cats__link" href="<?= $cat->getUrl() ?>" aria-haspopup="true" aria-expanded="false">
                    <span class="blog-cats__title"><?= Yii::t('database', $cat->name) ?></span>
                    <span class="blog-cats__caret" aria-hidden="true"></span>
                </a>

                <?php if (!empty($cat->children)): ?>
                    <div class="blog-subcats">
                        <ul class="blog-subcats_content" role="menu">
                            <?php foreach ($cat->children as $sub): ?>
                                <li class="blog-subcats__item" role="none">
                                    <a class="blog-subcats__link" role="menuitem" href="<?= $sub->getUrl() ?>">
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
