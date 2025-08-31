<?php
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap><loc><?= Yii::$app->params['homePage'] ?>/sitemap-main.xml</loc></sitemap>
    <sitemap><loc><?= Yii::$app->params['homePage'] ?>/sitemap-servers.xml</loc></sitemap>
    <sitemap><loc><?= Yii::$app->params['homePage'] ?>/sitemap-posts.xml</loc></sitemap>
</sitemapindex>