<?php

use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\settings\Settings;

/** @var Blog[] $articles */
/** @var BlogCategory $category */

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL ?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title><?php if (!empty($category)): ?><?=Yii::t('database', $category->name)?><?php else: ?><?=Settings::getByKey('title')?><?php endif; ?></title>
        <link><?=Yii::$app->params['homePage']?></link>
        <description><?php if (!empty($category)): ?><?=Yii::t('database', $category->description)?><?php else: ?><?=Settings::getByKey('description')?><?php endif; ?></description>
        <language><?=Yii::$app->params['language']?></language>
        <?php if (!empty($articles)): ?>
            <?php foreach ($articles as $blog): ?>
                <item>
                    <title><?=Yii::t('database', $blog->name)?></title>
                    <description><?=trim(Yii::t('database', $blog->description))?></description>
                    <link><?=Yii::$app->params['homePage'] . $blog->getUrl(); ?></link>
                    <?php if (!empty($blog->blogCategory)): ?>
                        <?php if (!empty($blog->blogCategory->parentCategory)): ?>
                            <category><?=\yii\helpers\Html::encode(Yii::t('database', $blog->blogCategory->parentCategory->name)); ?></category>
                        <?php endif; ?>
                        <category><?=\yii\helpers\Html::encode(Yii::t('database', $blog->blogCategory->name)); ?></category>
                    <?php endif; ?>
                    <author><?=trim(Yii::t('database', $blog->user->userProfile->full_name))?></author>
                    <pubDate><?=date('c', strtotime($blog->created_at))?></pubDate>
                </item>
            <?php endforeach; ?>
        <?php endif; ?>
    </channel>
</rss>