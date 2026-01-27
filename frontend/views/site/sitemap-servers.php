<?php
use yii\helpers\Url;
/** @var \common\models\servers\Servers[] $servers */
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($servers as $server): ?>
        <?php
        $serverUrl   = Yii::$app->params['homePage'] . $server->getLink('stats');   // например /servers/max3
        $rulesUrl    = Yii::$app->params['homePage'] . $server->getLink('rules');   // /servers/max3/rules
        $mapsUrl     = Yii::$app->params['homePage'] . $server->getLink('maps');    // /maps/max3
        ?>
        <url>
            <loc><?= htmlspecialchars($serverUrl, ENT_QUOTES) ?></loc>
            <changefreq>daily</changefreq>
            <priority>0.9</priority>
        </url>
        <url>
            <loc><?= htmlspecialchars($rulesUrl, ENT_QUOTES) ?></loc>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
        <url>
            <loc><?= htmlspecialchars($mapsUrl, ENT_QUOTES) ?></loc>
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>
</urlset>
