<?php
use yii\helpers\Url;
use common\models\radio\RadioStation;

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php
    $stations = RadioStation::find()
        ->where(['status' => RadioStation::STATUS_ACTIVE])
        ->orderBy(['id' => SORT_ASC])
        ->all();
    
    foreach ($stations as $station):
        $loc = Url::to(['radio/station', 'id' => $station->id], true);
        ?>
        <url>
            <loc><?= htmlspecialchars($loc, ENT_QUOTES) ?></loc>
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
            <?php if ($station->updated_at): ?>
            <lastmod><?= date('Y-m-d', strtotime($station->updated_at)) ?></lastmod>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
</urlset>

