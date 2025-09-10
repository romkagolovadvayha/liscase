<?php
use yii\helpers\Url;
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php
    $urls = [
        ['/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['/servers', 'priority' => '0.9'],
        ['/posts', 'priority' => '0.9'],
        ['/wipe-calendar', 'priority' => '0.9'],
        ['/raid-table', 'priority' => '0.9'],
        ['/skindrops', 'priority' => '0.9'],
        ['/custom-skins', 'priority' => '0.9'],
        ['/buildings', 'priority' => '0.9'],
    ];
    foreach ($urls as $u):
        $loc = Url::to($u[0], true);
        ?>
        <url>
            <loc><?= htmlspecialchars($loc, ENT_QUOTES) ?></loc>
            <?php if (!empty($u['changefreq'])): ?><changefreq><?= $u['changefreq'] ?></changefreq><?php endif; ?>
            <?php if (!empty($u['priority'])): ?><priority><?= $u['priority'] ?></priority><?php endif; ?>
        </url>
    <?php endforeach; ?>
</urlset>
