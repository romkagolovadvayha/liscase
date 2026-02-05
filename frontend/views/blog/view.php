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

// Add comment count
$commentCount = count($blog->comments);
if ($commentCount > 0) {
    $articleLd['commentCount'] = $commentCount;
    $articleLd['interactionStatistic'] = [
        '@type' => 'InteractionCounter',
        'interactionType' => 'https://schema.org/CommentAction',
        'userInteractionCount' => $commentCount
    ];
}

// Add word count (approximate)
$wordCount = str_word_count(strip_tags($blog->content));
if ($wordCount > 0) {
    $articleLd['wordCount'] = $wordCount;
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

<article id="<?=$blog->id?>" class="blog-post" itemscope itemtype="https://schema.org/Article">
    <!-- Post header -->
    <div class="blog-post_header">
        <div class="blog-post_meta">
            <div class="blog-post_date">
                <i class="far fa-calendar-alt"></i>
                <time datetime="<?=$date->format('c')?>" itemprop="datePublished" content="<?=$date->format('c')?>">
                    <?=$date->format('d.m.Y, H:i')?>
                </time>
            </div>
            
            <div class="blog-post_categories">
                <?php if (!empty($blog->blogCategory->parentCategory)): ?>
                    <a href="<?=$blog->blogCategory->parentCategory->getUrl()?>" class="blog-post_category">
                        <i class="fas fa-folder"></i>
                        <?=Yii::t('database', $blog->blogCategory->parentCategory->name)?>
                    </a>
                <?php endif; ?>
                
                <a href="<?=$blog->blogCategory->getUrl()?>" class="blog-post_category blog-post_category--active">
                    <i class="fas fa-tag"></i>
                    <?=Yii::t('database', $blog->blogCategory->name)?>
                </a>
            </div>
            
            <div class="blog-post_views">
                <i class="far fa-eye"></i>
                <span itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                    <meta itemprop="interactionType" content="https://schema.org/ViewAction"/>
                    <meta itemprop="userInteractionCount" content="<?=$blog->views?>"/>
                    <?= number_format($blog->views, 0, '.', ' ') ?>
                </span>
            </div>
        </div>
        
        <h1 class="blog-post_title" itemprop="headline"><?=Yii::t('database', $blog->name)?></h1>
    </div>
    
    <!-- Post content -->
    <div class="blog-post_content" itemprop="articleBody">
        <?php if (strtotime($blog->created_at) < strtotime('2025-08-28 12:00')): ?>
            <?php if (!empty($blog->blogImages) && count($blog->blogImages) > 1): ?>
                <div class="blog-post_gallery">
                    <?php foreach (array_slice($blog->blogImages, 1) as $image): ?>
                        <?=$this->render('_file', [
                            'url' => $image->getPublicUrl(),
                            'name' => $image->link
                        ]); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="tinymce-content">
            <?=Yii::t('database', $blog->content)?>
        </div>
        
        <?php if ($blog->created_at < '2025-03-30 00:00:00'): ?>
            <div class="blog-post_source">
                <i class="fas fa-info-circle"></i>
                <?=Yii::t('common', 'Поставщик новостей')?>: 
                <a href="https://discord.gg/rust-ru" rel="nofollow" target="_blank">RustRu</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Post footer -->
    <div class="blog-post_footer">
        <div class="blog-post_stats">
            <div class="blog-post_stat">
                <i class="far fa-eye"></i>
                <span><?= number_format($blog->views, 0, '.', ' ') ?></span>
                <span class="blog-post_stat_label"><?= Yii::t('common', 'просмотров') ?></span>
            </div>
            
            <div class="blog-post_stat">
                <i class="far fa-comment"></i>
                <span><?= count($blog->comments) ?></span>
                <span class="blog-post_stat_label"><?= Yii::t('common', 'комментариев') ?></span>
            </div>
        </div>
        
        <!-- Share buttons -->
        <div class="blog-post_share">
            <div class="blog-post_share_label">
                <i class="fas fa-share-alt"></i>
                <?= Yii::t('common','Поделиться') ?>:
            </div>
            
            <div class="blog-post_share_buttons">
                <!-- Telegram -->
                <a href="https://t.me/share/url?url=<?= urlencode($canonical) ?>&text=<?= urlencode(Yii::t('database', $blog->name)) ?>"
                   target="_blank" 
                   rel="nofollow noopener"
                   onclick="return openShare(this.href);"
                   class="blog-share-btn blog-share-btn--telegram"
                   title="Telegram">
                    <i class="fab fa-telegram-plane"></i>
                </a>

                <!-- VK -->
                <a href="https://vk.com/share.php?url=<?= urlencode($canonical) ?>&title=<?= urlencode(Yii::t('database', $blog->name)) ?>"
                   target="_blank" 
                   rel="nofollow noopener"
                   onclick="return openShare(this.href);"
                   class="blog-share-btn blog-share-btn--vk"
                   title="VK">
                    <i class="fab fa-vk"></i>
                </a>

                <!-- X (Twitter) -->
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode($canonical) ?>&text=<?= urlencode(Yii::t('database', $blog->name)) ?>"
                   target="_blank" 
                   rel="nofollow noopener"
                   onclick="return openShare(this.href);"
                   class="blog-share-btn blog-share-btn--twitter"
                   title="X (Twitter)">
                    <i class="fab fa-x-twitter"></i>
                </a>

                <!-- WhatsApp -->
                <a href="https://api.whatsapp.com/send?text=<?= urlencode(Yii::t('database', $blog->name) . ' — ' . $canonical) ?>"
                   target="_blank" 
                   rel="nofollow noopener"
                   onclick="return openShare(this.href);"
                   class="blog-share-btn blog-share-btn--whatsapp"
                   title="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>

                <!-- Copy link -->
                <button type="button"
                        onclick="copyShareLink('<?= htmlspecialchars($canonical, ENT_QUOTES) ?>')"
                        class="blog-share-btn blog-share-btn--copy"
                        title="<?= Yii::t('common','Скопировать ссылку') ?>">
                    <i class="fas fa-link"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden meta for SEO -->
    <div itemprop="author" itemscope itemtype="https://schema.org/Person">
        <meta itemprop="name" content="<?= $author ?>">
    </div>
    <meta itemprop="dateModified" content="<?= $updated ?>">
    <?php if (!empty($blog->blogImages[0])): ?>
        <meta itemprop="image" content="<?= $blog->blogImages[0]->getPublicUrl() ?>">
    <?php endif; ?>
    <div itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
        <meta itemprop="name" content="<?= $siteName ?>">
        <div itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
            <meta itemprop="url" content="<?= $logoUrl ?>">
        </div>
    </div>
</article>

<!-- Similar posts section -->
<section class="blog-similar-posts-section">
    <?=$this->render('../layouts/_side_similar_posts', ['model' => $this->params['_blog_model']]);?>
</section>

<!-- Comments section -->
<section id="comments" class="blog-comments-section">
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
</section>
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

<?= $this->render('@frontend/views/layouts/_wipe-calendar-promo-script') ?>