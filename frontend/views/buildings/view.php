<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;
use frontend\assets\BuildingsAsset;
use lo\widgets\magnific\MagnificPopup;
use common\models\building\Building;
use common\models\building\BuildingLike;
use common\models\user\UserRaid;

/** @var yii\web\View $this */
/** @var Building $model */

BuildingsAsset::register($this);

$isActive = BuildingLike::find()
                    ->andWhere(['building_id' => $model->id])
                    ->andWhere(['user_id' => Yii::$app->user->id])
                    ->exists();

// Получаем информацию о рейдах базы
$raids = UserRaid::find()
    ->where(['location' => $model->location])
    ->andWhere(['server_id' => $model->server->id])
    ->andWhere(['wipe' => $model->wipe])
    ->with('user')
    ->orderBy(['created_at' => SORT_DESC])
    ->limit(10)
    ->all();

$raidCount = UserRaid::find()
    ->where(['location' => $model->location])
    ->andWhere(['server_id' => $model->server->id])
    ->andWhere(['wipe' => $model->wipe])
    ->count();

// Подсчитываем уникальные взрывчатки
$allExplosives = [];
foreach ($raids as $raid) {
    if (!empty($raid->explosives)) {
        $explosives = json_decode($raid->explosives, true);
        if (is_array($explosives)) {
            $allExplosives = array_merge($allExplosives, $explosives);
        }
    }
}
$uniqueExplosives = array_unique($allExplosives);

// Общее количество изображений
$imageCount = count($model->buildingImage);
$residentCount = count($model->buildingResident);

// Получаем другие постройки жильцов
$residentIds = [];
foreach ($model->buildingResident as $resident) {
    $residentIds[] = $resident->user_id;
}

// Если есть жильцы, находим их другие постройки
$otherBuildings = [];
if (!empty($residentIds)) {
    $otherBuildings = Building::find()
        ->joinWith('buildingResident')
        ->where(['building_resident.user_id' => $residentIds])
        ->andWhere(['!=', 'building.id', $model->id])
        ->andWhere(['building.status' => Building::STATUS_ACTIVE])
        ->groupBy(['building.id'])
        ->with(['buildingImage', 'server', 'user'])
        ->orderBy(['building.created_at' => SORT_DESC])
        ->limit(6)
        ->all();
}

// SEO настройки
$buildingName = Yii::t('database', $model->name);
$serverName = Yii::t('database', $model->server->monitoring_name);
$this->title = $buildingName . ' - Постройка на сервере ' . $serverName . ' | Rust';

$description = $model->description 
    ? mb_substr(strip_tags($model->description), 0, 155) . '...'
    : "Постройка {$buildingName} от игрока {$model->user->username} на сервере {$serverName}. Посмотрите скриншоты, узнайте местоположение и оцените работу строителя.";

$this->registerMetaTag(['name' => 'description', 'content' => $description]);
$this->registerMetaTag(['name' => 'keywords', 'content' => "rust база {$buildingName}, постройка rust, {$serverName}, база rust"]);

// Open Graph
$imageUrl = !empty($model->buildingImage) ? Url::to($model->buildingImage[0]->getPublicUrl(), true) : '';
$this->registerMetaTag(['property' => 'og:title', 'content' => $this->title]);
$this->registerMetaTag(['property' => 'og:description', 'content' => $description]);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'article']);
if ($imageUrl) {
    $this->registerMetaTag(['property' => 'og:image', 'content' => $imageUrl]);
}

// Структурированные данные Schema.org
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CreativeWork',
    'name' => $buildingName,
    'description' => $model->description ?: $description,
    'creator' => [
        '@type' => 'Person',
        'name' => $model->user->username
    ],
    'datePublished' => date('c', is_numeric($model->created_at) ? $model->created_at : strtotime($model->created_at)),
    'interactionStatistic' => [
        '@type' => 'InteractionCounter',
        'interactionType' => 'https://schema.org/LikeAction',
        'userInteractionCount' => $model->likes
    ]
];

if ($imageUrl) {
    $schema['image'] = $imageUrl;
}

$this->registerMetaTag(['name' => 'schema', 'content' => json_encode($schema, JSON_UNESCAPED_UNICODE)], 'schema');
?>
<!-- Хлебные крошки -->
<nav class="breadcrumbs" aria-label="breadcrumb">
    <?= Html::a(Yii::t('common', 'Главная'), ['/'], ['class' => 'breadcrumbs_item']) ?>
    <span class="breadcrumbs_separator">/</span>
    <?= Html::a(Yii::t('common', 'Постройки'), ['index'], ['class' => 'breadcrumbs_item']) ?>
    <span class="breadcrumbs_separator">/</span>
    <span class="breadcrumbs_item breadcrumbs_item--active"><?= $buildingName ?></span>
</nav>

<article class="building-view">
    <div class="building-view_container">
        <!-- Заголовок с кнопками -->
        <header class="building-view_header">
            <div class="building-view_header_title">
                <h1><?= $buildingName ?></h1>
                <div class="building-view_header_badges">
                    <span class="building-badge building-badge--wipe">
                        <i class="fa-solid fa-history"></i> Вайп <?= $model->wipe ?>
                    </span>
                    <?php if ($raidCount > 0): ?>
                        <span class="building-badge building-badge--raid">
                            <i class="fa-solid fa-bomb"></i> <?= $raidCount ?> <?= Yii::t('common', 'рейдов') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="building-view_header_actions">
                <?= Html::a(
                    '<i class="fa-solid fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
                    ['index'],
                    ['class' => 'button button-secondary']
                ) ?>
            </div>
        </header>

        <!-- Статистические карточки -->
        <div class="building-view_stats">
            <div class="stat-card">
                <div class="stat-card_icon stat-card_icon--server">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div class="stat-card_content">
                    <div class="stat-card_label"><?= Yii::t('common', 'Сервер') ?></div>
                    <div class="stat-card_value"><?= $serverName ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card_icon stat-card_icon--location">
                    <i class="fa-solid fa-map-marker-alt"></i>
                </div>
                <div class="stat-card_content">
                    <div class="stat-card_label"><?= Yii::t('common', 'Квадрат') ?></div>
                    <div class="stat-card_value"><?= $model->location ?></div>
                </div>
            </div>
            
            <?php if ($residentCount > 0): ?>
                <div class="stat-card">
                    <div class="stat-card_icon stat-card_icon--residents">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-card_content">
                        <div class="stat-card_label"><?= Yii::t('common', 'Жильцов') ?></div>
                        <div class="stat-card_value"><?= $residentCount ?></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-card_icon stat-card_icon--images">
                    <i class="fa-solid fa-images"></i>
                </div>
                <div class="stat-card_content">
                    <div class="stat-card_label"><?= Yii::t('common', 'Скриншотов') ?></div>
                    <div class="stat-card_value"><?= $imageCount ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card_icon stat-card_icon--user">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="stat-card_content">
                    <div class="stat-card_label"><?= Yii::t('common', 'Автор') ?></div>
                    <div class="stat-card_value">
                        <?= Html::a($model->user->username, $model->user->getLink('stats'), [
                            'target' => '_blank', 
                            'rel' => 'nofollow',
                            'class' => 'stat-card_link'
                        ]) ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card_icon stat-card_icon--time">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="stat-card_content">
                    <div class="stat-card_label"><?= Yii::t('common', 'Добавлено') ?></div>
                    <div class="stat-card_value"><?= $model->passed() ?></div>
                </div>
            </div>
        </div>

        <!-- Основной контент (одна колонка) -->
        <div class="building-view_content">
                <?php if (!empty($model->buildingImage)): ?>
                    <section class="building-gallery">
                        <h2 class="section-title">
                            <i class="fa-solid fa-images"></i>
                            <?= Yii::t('common', 'Галерея постройки') ?>
                            <span class="section-title_count"><?= $imageCount ?></span>
                        </h2>
                        
                        <div class="building-gallery_grid" id="mpup">
                            <?php foreach ($model->buildingImage as $i => $image): ?>
                                <a href="<?= $image->getPublicUrl() ?>" 
                                   title="<?= $buildingName ?> - Скриншот <?= $i + 1 ?>"
                                   class="building-gallery_item">
                                    <img src="<?= $image->getPublicUrlPreview() ?>" 
                                         alt="<?= $buildingName ?> - Скриншот <?= $i + 1 ?>"
                                         loading="lazy">
                                    <div class="building-gallery_item_overlay">
                                        <i class="fa-solid fa-search-plus"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Описание -->
                <?php if ($model->description): ?>
                    <section class="building-description">
                        <h2 class="section-title">
                            <i class="fa-solid fa-align-left"></i>
                            <?= Yii::t('common', 'Описание постройки') ?>
                        </h2>
                        <div class="building-description_text">
                            <?= nl2br(Html::encode($model->description)) ?>
                        </div>
                    </section>
                <?php endif; ?>

            <!-- Жильцы -->
            <?php if (!empty($model->buildingResident)): ?>
                <section class="building-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-users"></i>
                        <?= Yii::t('common', 'Жильцы базы') ?>
                        <span class="section-title_count"><?= $residentCount ?></span>
                    </h2>
                    <div class="building-users_grid">
                        <?php foreach ($model->buildingResident as $resident): ?>
                            <a title="<?= Yii::t('common', 'Открыть профиль игрока') ?> <?= $resident->user->username ?>"
                               target="_blank"
                               href="/stats/player?steamId=<?= $resident->user->steam_id ?>&server=<?= $model->server_tag ?>"
                               class="building-user_card">
                                <img src="<?= $resident->user->getAvatar() ?>" 
                                     alt="<?= $resident->user->username ?>"
                                     loading="lazy"/>
                                <span class="building-user_card_name"><?= $resident->user->username ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Оценки с кнопкой лайка -->
            <section class="building-section building-likes_section">
                <div class="building-likes_header">
                    <h2 class="section-title">
                        <i class="fa-solid fa-heart"></i>
                        <?= Yii::t('common', 'Оценили постройку') ?>
                        <span class="section-title_count"><?= $model->likes ?></span>
                    </h2>
                    <div class="building-view_like">
                        <button type="button" 
                                class="buildings_content_list_item_images_like<?= ($isActive) ? ' active' : '' ?>" 
                                data-id="<?= $model->id ?>" 
                                data-guest="<?= Yii::$app->user->isGuest ? 1 : 0 ?>"
                                title="<?= Yii::t('common', 'Оценить постройку') ?>">
                            <span class="buildings_content_list_item_images_like_count"><?= $model->likes ?></span>
                        <span class="buildings_content_list_item_images_like_icon">
                                            <i class="icon_active fa-solid fa-heart"></i>
                                            <i class="icon_noactive fa-regular fa-heart"></i>
                                    </span>
                        </button>
                    </div>
                    </div>
                
                <?php if (!empty($model->buildingLikes)): ?>
                    <div class="building-users_grid">
                    <?php foreach ($model->buildingLikes as $like): ?>
                            <a title="<?= Yii::t('common', 'Открыть профиль игрока') ?> <?= $like->user->username ?>"
                            target="_blank"
                               href="<?= $like->user->getLink('stats') ?>"
                            rel="nofollow"
                               class="building-user_card">
                                <img src="<?= $like->user->getAvatar() ?>" 
                                     alt="<?= $like->user->username ?>"
                                     loading="lazy"/>
                                <span class="building-user_card_name"><?= $like->user->username ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <p class="building-empty_text"><?= Yii::t('common', 'Станьте первым, кто оценит эту постройку!') ?></p>
                <?php endif; ?>
            </section>

            <!-- Другие постройки жильцов -->
            <?php if (!empty($otherBuildings)): ?>
                <section class="related-buildings">
                    <h2 class="section-title">
                        <i class="fa-solid fa-building"></i>
                        <?= Yii::t('common', 'Другие постройки жильцов') ?>
                        <span class="section-title_count"><?= count($otherBuildings) ?></span>
                    </h2>
                    
                    <div class="related-buildings_grid">
                        <?php foreach ($otherBuildings as $building): ?>
                            <?php if (!empty($building->buildingImage)): ?>
                                <a href="<?= Url::to(['/buildings/view', 'id' => $building->id]) ?>" 
                                   class="related-building_card"
                                   title="<?= Yii::t('database', $building->name) ?>">
                                    <div class="related-building_card_image">
                                        <img src="<?= $building->buildingImage[0]->getPublicUrlPreview() ?>" 
                                             alt="<?= Yii::t('database', $building->name) ?>"
                                             loading="lazy">
                                        <div class="related-building_card_overlay">
                                            <div class="related-building_card_name">
                                                <?= Yii::t('database', $building->name) ?>
                                            </div>
                                            <div class="related-building_card_server">
                                                <i class="fa-solid fa-server"></i>
                                                <?= Yii::t('database', $building->server->monitoring_name) ?>
                                            </div>
                                        </div>
                                        <?php if ($building->likes > 0): ?>
                                            <div class="related-building_card_likes">
                                                <i class="fa-solid fa-heart"></i>
                                                <?= $building->likes ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="related-building_card_footer">
                                        <div class="related-building_card_author">
                                            <img src="<?= $building->user->getAvatar() ?>" 
                                                 alt="<?= $building->user->username ?>">
                                            <span><?= $building->user->username ?></span>
                                        </div>
                                        <div class="related-building_card_date">
                                            <?= $building->passed() ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Рейды -->
            <?php if (!empty($raids)): ?>
                <section class="building-section building-raids_section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-bomb"></i>
                        <?= Yii::t('common', 'История рейдов') ?>
                        <span class="section-title_count"><?= $raidCount ?></span>
                    </h2>
                    
                    <?php if (!empty($uniqueExplosives)): ?>
                        <div class="raid-explosives">
                            <div class="raid-explosives_label"><?= Yii::t('common', 'Использованные взрывчатки') ?>:</div>
                            <div class="raid-explosives_list">
                                <?php foreach ($uniqueExplosives as $explosive): ?>
                                    <span class="raid-explosive_tag"><?= $explosive ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                    <?php endif; ?>
                    
                    <div class="raid-timeline">
                        <?php foreach (array_slice($raids, 0, 5) as $raid): ?>
                            <div class="raid-timeline_item">
                                <div class="raid-timeline_icon">
                                    <i class="fa-solid fa-explosion"></i>
                                </div>
                                <div class="raid-timeline_content">
                                    <?php if ($raid->user): ?>
                                        <div class="raid-timeline_user">
                                            <img src="<?= $raid->user->getAvatar() ?>" alt="<?= $raid->user->username ?>">
                                            <a href="<?= $raid->user->getLink('stats') ?>" 
                            target="_blank"
                                               rel="nofollow">
                                                <?= $raid->user->username ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="raid-timeline_type">
                                        <?php if ($raid->type == 'cupboard'): ?>
                                            <i class="fa-solid fa-house-damage"></i> <?= Yii::t('common', 'Шкаф уничтожен') ?>
                                        <?php else: ?>
                                            <i class="fa-solid fa-door-open"></i> <?= ucfirst($raid->type) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="raid-timeline_date">
                                        <i class="fa-solid fa-clock"></i>
                                        <?= Yii::$app->formatter->asRelativeTime($raid->created_at) ?>
                </div>
            </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($raidCount > 5): ?>
                            <div class="raid-timeline_more">
                                <?= Yii::t('common', 'и ещё') ?> <?= $raidCount - 5 ?> <?= Yii::t('common', 'рейдов') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</article>
<?= MagnificPopup::widget([
        'target' => '#mpup',
        'options' => [
        'delegate' => 'a',
            'gallery' => [
                'enabled' => true
            ],
        ],
    'effect' => 'with-zoom'
]) ?>