<?php
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use yii\helpers\Html;

// ВАЖНО: корректный заголовок
header('Content-Type: application/rss+xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>
            <?php if (!empty($category)): ?>
                <?= Html::encode(Yii::t('database', $category->name)) ?>
            <?php else: ?>
                <?= Html::encode(Yii::t('database', 'Простой проект серверов Rust')) ?>
            <?php endif; ?>
        </title>

        <link><?= Html::encode(Yii::$app->params['homePage']) ?></link>

        <description>
            <?php if (!empty($category)): ?>
                <?= Html::encode(Yii::t('database', $category->description)) ?>
            <?php else: ?>
                <?= Html::encode(Yii::t('database', 'Сервера Rust для новичков')) ?>
            <?php endif; ?>
        </description>

        <language><?= Html::encode(Yii::$app->params['language'] ?? 'ru') ?></language>

        <?php if (!empty($articles)): ?>
            <?php foreach ($articles as $blog): ?>
                <?php
                $itemTitle = Html::encode(Yii::t('database', $blog->name));
                $itemLink  = Yii::$app->params['homePage'] . $blog->getUrl();
                $rawDesc = trim(Yii::t('database', $blog->description));
                $itemDesc = Html::encode($rawDesc);
                $pubDate  = gmdate('r', strtotime($blog->created_at));
                $author   = !empty($blog->user->userProfile->full_name)
                    ? Html::encode(trim(Yii::t('database', $blog->user->userProfile->full_name)))
                    : 'Unknown';
                ?>
                <item>
                    <title><?= $itemTitle ?></title>
                    <description><?= $itemDesc ?></description>
                    <link><?= Html::encode($itemLink) ?></link>
                    <guid isPermaLink="true"><?= Html::encode($itemLink) ?></guid>

                    <?php if (!empty($blog->blogCategory)): ?>
                        <?php if (!empty($blog->blogCategory->parentCategory)): ?>
                            <category><?= Html::encode(Yii::t('database', $blog->blogCategory->parentCategory->name)) ?></category>
                        <?php endif; ?>
                        <category><?= Html::encode(Yii::t('database', $blog->blogCategory->name)) ?></category>
                    <?php endif; ?>

                    <!-- В RSS 2.0 <author> — это обычно email. Лучше dc:creator -->
                    <dc:creator><?= $author ?></dc:creator>
                    <pubDate><?= $pubDate ?></pubDate>
                </item>
            <?php endforeach; ?>
        <?php endif; ?>
    </channel>
</rss>
