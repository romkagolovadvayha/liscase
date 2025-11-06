<?php
use yii\helpers\Url;

/** @var \common\models\servers\ServersTags[] $tags */
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($tags as $tag): ?>
        <?php
        $tagUrl = Yii::$app->params['homePage'] . $tag->getLink();
        ?>
        <url>
            <loc><?= htmlspecialchars($tagUrl, ENT_QUOTES) ?></loc>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
            <?php if ($tag->updated_at): ?>
            <lastmod><?= date('Y-m-d', strtotime($tag->updated_at)) ?></lastmod>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
</urlset>

