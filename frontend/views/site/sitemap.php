<?php

use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;

/** @var Blog[] $articles */
/** @var BlogCategory[] $categories */
/** @var \common\models\servers\Servers[] $servers */

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL ?>
<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?=Yii::$app->params['homePage']?></loc>
        <priority>1.0</priority>
        <changefreq>daily</changefreq>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/servers</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/skindrops</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/servers</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/banlist</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/posts</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/buildings</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/support</loc>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?=Yii::$app->params['homePage']?>/referral</loc>
        <priority>0.9</priority>
    </url>
    <?php foreach ($servers as $server): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?=$server->getLink('stats')?></loc>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>
    <?php foreach ($servers as $server): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?=$server->getLink('maps')?></loc>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>
    <?php foreach ($servers as $server): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?=$server->getLink('rules')?></loc>
            <priority>0.7</priority>
        </url>
    <?php endforeach; ?>
    <?php foreach ($categories as $category): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?= $category->getUrl() ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($category->created_at)) ?></lastmod>
            <?php if (empty($category->parentCategory)): ?>
                <priority>0.8</priority>
            <?php else: ?>
                <priority>0.7</priority>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
    <?php foreach ($articles as $article): ?>
        <url>
            <loc><?=Yii::$app->params['homePage']?><?= $article->getUrl() ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($article->created_at)) ?></lastmod>
            <priority>0.6</priority>
        </url>
    <?php endforeach; ?>
</urlset>