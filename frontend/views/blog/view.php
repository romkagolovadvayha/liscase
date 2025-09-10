<?php

use yii\web\View;
use frontend\widgets\Alert;
use common\models\blog\Blog;
use yii\bootstrap5\Breadcrumbs;

/** @var View $this */
/** @var Blog $blog */

$this->title = Yii::t('database', $blog->name);
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', "Блог"), 'url' => ["/posts"]];
if (!empty($blog->blogCategory->parentCategory)) {
    $this->params['breadcrumbs'][] = ['label' => Yii::t('database', $blog->blogCategory->parentCategory->name), 'url' => [$blog->blogCategory->parentCategory->getUrl()]];
}
$this->params['breadcrumbs'][] = ['label' => Yii::t('database', $blog->blogCategory->name), 'url' => [$blog->blogCategory->getUrl()]];
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_keywords'] = Yii::t('database', $blog->keywords);
$this->params['meta_description'] = Yii::t('database', $blog->description);
$this->params['page'] = 'blog';

$date = new DateTime($blog->created_at);
$ratings = $blog->blogRatings;
$rating = 0;
foreach ($ratings as $_rating) {
    $rating += $_rating->weight;
}

$this->params['_blog_category'] = $blog->blogCategory;
$this->params['_blog_model'] = $blog;
$this->params['_blog_comments_block'] = true;
$this->params['_blog_category_block'] = true;
$this->params['_blog_similar_block'] = true;

$cookieName   = "blog_viewed_{$blog->id}";
$cooldownMins = 360; // 6 часов, можно поставить 1440 (сутки)

if (!\common\components\web\Cookie::getValue($cookieName)) {
    Blog::updateAllCounters(['views' => 1], ['id' => $blog->id]);
    \common\components\web\Cookie::add($cookieName, 1, false, $cooldownMins);
}


/** @var \common\models\blog\Blog $blog */
$canonical = Yii::$app->params['homePage'] . $blog->getUrl();
$locale    = Yii::$app->language;
$siteName  = 'Prostoj';
$ogTitle   = Yii::t('database', $blog->name);
$ogDesc    = Yii::t('database', $blog->description ?: mb_substr(strip_tags($blog->content), 0, 180));
$ogImg     = !empty($blog->blogImages[0]) ? $blog->blogImages[0]->getPublicUrl() : null;
$published = (new DateTime($blog->created_at))->format('Y-m-d\TH:i:sP');
$updated   = (new DateTime($blog->created_at))->format('Y-m-d\TH:i:sP');
$author    = $blog->user->username ?? 'Prostoj';
$logoUrl   = Yii::$app->params['homePage'] . Yii::$app->settings->get('design_logo');

// 1) canonical + hreflang (оставляем как у тебя, добавим canonical)
$this->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);

// 2) robots-подсказки (увеличивают шансы на богатые сниппеты и крупные превью)
$this->registerMetaTag(['name' => 'robots', 'content' => 'index,follow,max-image-preview:large']);

// 3) Open Graph
$this->registerMetaTag(['property' => 'og:type', 'content' => 'article']);
$this->registerMetaTag(['property' => 'og:locale', 'content' => $locale]);
$this->registerMetaTag(['property' => 'og:site_name', 'content' => $siteName]);
$this->registerMetaTag(['property' => 'og:title', 'content' => $ogTitle]);
$this->registerMetaTag(['property' => 'og:description', 'content' => $ogDesc]);
$this->registerMetaTag(['property' => 'og:url', 'content' => $canonical]);
if (!empty($ogImg)) {
    $this->registerMetaTag(['property' => 'og:image', 'content' => $ogImg]);
}

// 4) Twitter Card
$this->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image']);
$this->registerMetaTag(['name' => 'twitter:title', 'content' => $ogTitle]);
$this->registerMetaTag(['name' => 'twitter:description', 'content' => $ogDesc]);
if (!empty($ogImg)) {
    $this->registerMetaTag(['name' => 'twitter:image', 'content' => $ogImg]);
}

// 5) JSON-LD: Article (+Publisher/Logo)
$articleLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $canonical
    ],
    'headline' => $ogTitle,
    'description' => $ogDesc,
    'datePublished' => $published,
    'dateModified' => $updated,
    'author' => ['@type' => 'Person', 'name' => $author],
    'publisher' => [
        '@type' => 'Organization',
        'name' => $siteName,
        'logo' => ['@type' => 'ImageObject', 'url' => $logoUrl]
    ]
];
$imgUrls = array_map(fn($i) => $i->getPublicUrl(), $blog->blogImages ?? []);
if (!empty($imgUrls) || !empty($ogImg)) {
    $articleLd['image'] = $imgUrls ?: [$ogImg];
}

// 6) JSON-LD: BreadcrumbList (по твоим хлебным крошкам)
$crumbs = [
    ['name'=>Yii::t('common','Блог'), 'url'=>Yii::$app->params['homePage'].'/posts'],
];
if (!empty($blog->blogCategory->parentCategory)) {
    $crumbs[] = ['name'=>Yii::t('database',$blog->blogCategory->parentCategory->name), 'url'=>Yii::$app->params['homePage'].$blog->blogCategory->parentCategory->getUrl()];
}
$crumbs[] = ['name'=>Yii::t('database',$blog->blogCategory->name), 'url'=>Yii::$app->params['homePage'].$blog->blogCategory->getUrl()];
$crumbs[] = ['name'=>Yii::t('database',$blog->name), 'url'=>$canonical];

$breadcrumbLd = [
    '@context'=>'https://schema.org',
    '@type'=>'BreadcrumbList',
    'itemListElement'=>array_map(function($c,$i){
        return ['@type'=>'ListItem','position'=>$i+1,'name'=>$c['name'],'item'=>$c['url']];
    }, $crumbs, array_keys($crumbs))
];
?>
<script type="application/ld+json">
<?= json_encode($articleLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
</script>
<script type="application/ld+json">
<?= json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
</script>

<?= Alert::widget() ?>
    <article id="<?=$blog->id?>" class="blog_item">
        <div class="blog_item_container_wrap">
        <div class="blog_item_snippet">
            <h1 class="blog_item_title"><?=Yii::t('database', $blog->name)?></h1>
            <div class="blog_item_categories">
                <a href="<?=$blog->blogCategory->parentCategory->getUrl()?>"><?=Yii::t('database', $blog->blogCategory->parentCategory->name)?></a>, <a href="<?=$blog->blogCategory->getUrl()?>"><?=Yii::t('database', $blog->blogCategory->name)?></a>
            </div>
            <div class="blog_item_container">
                <div class="blog_item_snippet_meta">
                            <span class="blog_item_snippet_meta_author">
                                <span class="blog_item_snippet_meta_author_user">
                                    <span class="blog_item_snippet_meta_author_user_published">
                                        <time datetime="<?=$date->format('c')?>" title="<?=$date->format('d.m.Y, H:i')?>"><?=$date->format('d.m.Y, H:i')?></time>
                                    </span>
                                </span>
                            </span>
                </div>
            </div>
            <div class="blog_item_body">
                <?php if (strtotime($blog->created_at) < strtotime('2025-08-28 12:00')): ?>
                    <?php if (!empty($blog->blogImages)): ?>
                    <div class="blog_item_body_text_images mb-24">
                        <?php foreach ($blog->blogImages as $image): ?>
                            <?=$this->render('_file', [
                                'url' => $image->getPublicUrl(),
                                'name' => $image->link
                            ]); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="mb-24 tinymce-content"><?=Yii::t('database', $blog->content)?></div>
                <?php if ($blog->created_at < '2025-03-30 00:00:00'): ?>
                    <p class="p2">
                        <?=Yii::t('common', 'Поставщик новостей')?>: <a href="https://discord.gg/rust-ru" rel="nofollow" class="p2" target="_blank">RustRu</a>
                    </p>
                <?php endif; ?>
            </div>
            <div class="blog_item_data">
                <div class="blog_item_data_item blog_item_data_views">
                    <div class="blog_item_data_item_icon_wrapper" title="<?=Yii::t('common', 'Количество просмотров')?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="blog_item_data_item_icon">
                            <path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/>
                        </svg>
                        <span><?=$blog->views?></span>
                    </div>
                </div>
                <?php
                $shareUrl   = $canonical;                               // уже вычисляется выше
                $shareTitle = Yii::t('database', $blog->name);
                $shareTxt   = $shareTitle . ' — ' . $shareUrl;
                ?>
                <div class="blog_item_data_item blog_item_data_share dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-inline-flex align-items-center"
                            type="button" id="shareDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <!-- share icon -->
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" class="me-1 blog_item_data_item_icon">
                            <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7a3.27 3.27 0 000-1.39l7-4.11A3 3 0 0018 7.91 3.09 3.09 0 1021.09 5 3.09 3.09 0 0018 7.91c-.45 0-.88-.1-1.26-.28l-7 4.12a3.09 3.09 0 100 4.5l7-4.12c.38.18.81.28 1.26.28A3.09 3.09 0 1021.09 16 3.09 3.09 0 0018 16.08z"/>
                        </svg>
                        <?= Yii::t('common','Поделиться') ?>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="shareDropdown" style="min-width: 260px">
                        <!-- Нативный share (мобилки) -->
                        <li>
                            <button class="dropdown-item" type="button"
                                    onclick="if (navigator.share) { navigator.share({title: '<?= addslashes($shareTitle) ?>', text: '<?= addslashes($shareTitle) ?>', url: '<?= $shareUrl ?>'}) } else { alert('Sharing not supported'); }">
                                📱 <?= Yii::t('common','Поделиться через приложение…') ?>
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>

                        <!-- Telegram -->
                        <li><a class="dropdown-item"
                               href="https://t.me/share/url?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($shareTitle) ?>"
                               target="_blank" rel="nofollow noopener"
                               onclick="return openShare(this.href);">Telegram</a></li>

                        <!-- VK -->
                        <li><a class="dropdown-item"
                               href="https://vk.com/share.php?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($shareTitle) ?>"
                               target="_blank" rel="nofollow noopener"
                               onclick="return openShare(this.href);">VK</a></li>

                        <!-- X (Twitter) -->
                        <li><a class="dropdown-item"
                               href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($shareTitle) ?>"
                               target="_blank" rel="nofollow noopener"
                               onclick="return openShare(this.href);">X (Twitter)</a></li>

                        <!-- WhatsApp -->
                        <li><a class="dropdown-item"
                               href="https://api.whatsapp.com/send?text=<?= urlencode($shareTxt) ?>"
                               target="_blank" rel="nofollow noopener"
                               onclick="return openShare(this.href);">WhatsApp</a></li>

                        <!-- Facebook -->
                        <li><a class="dropdown-item"
                               href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>"
                               target="_blank" rel="nofollow noopener"
                               onclick="return openShare(this.href);">Facebook</a></li>

                        <li><hr class="dropdown-divider"></li>

                        <!-- Копировать ссылку -->
                        <li>
                            <button class="dropdown-item" type="button" onclick="copyShareLink('<?= htmlspecialchars($shareUrl, ENT_QUOTES) ?>')">
                                🔗 <?= Yii::t('common','Скопировать ссылку') ?>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        </div>
    </article>
        <?=$this->render('../layouts/_side_similar_posts', ['model' => $this->params['_blog_model']]);?>
    <div id="comments" class="mt-12">
        <?php echo \yii2mod\comments\widgets\Comment::widget([
                                                                 'model' => $blog,
                                                                 'commentView' => '@frontend/views/blog/comments/index',
                                                                 'maxLevel' => 2,
                                                                 'dataProviderConfig' => [
                                                                     'pagination' => [
                                                                         'pageSize' => 10
                                                                     ],
                                                                 ],
                                                                 'listViewConfig' => [
                                                                     'emptyText' => Yii::t('common', 'Нет комментариев.'),
                                                                 ],
                                                             ]); ?>
    </div>
<?=\lo\widgets\magnific\MagnificPopup::widget(
    [
        'target' => '.blog_item_body_text_images_item_preview_wrap',
        'options' => [
            'delegate'=> 'a',
            'gallery' => [
                'enabled' => true
            ],
        ],
        'effect' => 'with-zoom' //for zoom effect
    ]
);?>
<script>
    function openShare(u){
        const w=640,h=500,
            y=window.top.outerHeight/2 + window.top.screenY - (h/2),
            x=window.top.outerWidth/2 + window.top.screenX - (w/2);
        const win = window.open(u, '_blank', `toolbar=0,status=0,width=${w},height=${h},top=${y},left=${x}`);
        if (win) win.focus();
        return false;
    }
    async function copyShareLink(url){
        try {
            await navigator.clipboard.writeText(url);
            // Можешь заменить на свой Alert::widget или тост
            alert('Ссылка скопирована 📋');
        } catch(e){
            // fallback
            const ta=document.createElement('textarea');
            ta.value=url; document.body.appendChild(ta); ta.select();
            try{ document.execCommand('copy'); alert('Ссылка скопирована 📋'); } finally { ta.remove(); }
        }
    }
</script>
