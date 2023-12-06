<?php

use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;

/** @var Blog[] $articles */
/** @var BlogCategory[] $categories */
/** @var User[] $users */

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL ?>
<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?=Yii::$app->params['homePage']?></loc>
        <priority>1.0</priority>
        <changefreq>daily</changefreq>
        <lastmod><?=date('Y-m-d')?></lastmod>
    </url>
    <?php foreach ($articles as $article): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?= $article->getUrl() ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($article->created_at)) ?></lastmod>
            <priority>0.9</priority>
        </url>
    <?php endforeach; ?>
    <?php foreach ($categories as $category): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?= $category->getUrl() ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($category->created_at)) ?></lastmod>
            <?php if (empty($category->parentCategory)): ?>
                <priority>0.9</priority>
            <?php else: ?>
                <priority>0.8</priority>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
    <?php foreach ($users as $user): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?>/users/<?= $user->username ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($user->created_at)) ?></lastmod>
            <priority>0.5</priority>
        </url>
    <?php endforeach; ?>
</urlset>