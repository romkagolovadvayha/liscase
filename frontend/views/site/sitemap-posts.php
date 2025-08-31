<?php
use yii\helpers\Url;
use common\models\blog\Blog;
/** @var Blog[] $articles */
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;

// Берём только опубликованные за последние 24–36 месяцев
$articles = Blog::find()
                ->where(['status' => Blog::STATUS_ACTIVE])
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-24 months'))])
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(5000) // на всякий случай
                ->all();
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($articles as $a): ?>
        <?php
        $loc = Yii::$app->params['homePage'] . $a->getUrl();
        $last = $a->created_at;
        // приоритет: свежее → выше
        $ageDays = (time() - strtotime($last)) / 86400;
        $priority = $ageDays <= 60 ? '1.0' : ($ageDays <= 365 ? '0.8' : '0.6');
        ?>
        <url>
            <loc><?= htmlspecialchars($loc, ENT_QUOTES) ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($last)) ?></lastmod>
            <priority><?= $priority ?></priority>
            <changefreq><?= $ageDays <= 60 ? 'daily' : 'weekly' ?></changefreq>
        </url>
    <?php endforeach; ?>
</urlset>
