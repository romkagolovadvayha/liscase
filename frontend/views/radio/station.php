<?php

use common\models\radio\RadioTrack;
use yii\helpers\Html;
use yii\widgets\ListView;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var common\models\radio\RadioStation $station */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var frontend\models\radio\RadioTrackSearch $searchModel */
/** @var bool $userTracksWait */

$tracksCount = $station->getApprovedTracks()->count();
$pageDescription = $station->description 
    ?: Yii::t('common', 'Слушайте музыку на радиостанции {name}. {count} треков в плейлисте.', [
        'name' => $station->name, 
        'count' => $tracksCount
    ]);

$this->title = $station->name . ' - ' . Yii::t('common', 'Радиостанция') . ' - ' . Yii::$app->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Радиостанции'), 'url' => ['radio/index']];
$this->params['breadcrumbs'][] = $station->name;

// SEO мета-теги
$this->registerMetaTag([
    'name' => 'description',
    'content' => $pageDescription
]);

// Open Graph мета-теги
$this->registerMetaTag([
    'property' => 'og:title',
    'content' => $this->title
]);
$this->registerMetaTag([
    'property' => 'og:description',
    'content' => $pageDescription
]);
$this->registerMetaTag([
    'property' => 'og:type',
    'content' => 'website'
]);
$this->registerMetaTag([
    'property' => 'og:url',
    'content' => \yii\helpers\Url::to(['radio/station', 'id' => $station->id], true)
]);

// Если есть текущий трек, добавляем дополнительную информацию
if ($station->currentTrack) {
    $this->registerMetaTag([
        'name' => 'twitter:card',
        'content' => 'player'
    ]);
}
?>

<?= \frontend\widgets\Alert::widget() ?>

<div class="server_info_page">
    <div class="radio-station-page">
        <div class="radio-station-header">
            <h1><?= Html::encode($station->name) ?></h1>
            <?php if ($station->description): ?>
                <p class="station-description"><?= Html::encode($station->description) ?></p>
            <?php endif; ?>
            
            <div class="station-controls">
                <?= Html::a(
                    '<i class="fa fa-upload"></i> ' . Yii::t('common', 'Загрузить трек'), 
                    ['create', 'stationId' => $station->id], 
                    [
                        'class' => 'button button-primary show-modal-link',
                        'data-title' => Yii::t('common', 'Загрузить трек'),
                        'data-size' => 'modal-md',
                        'data-toggl' => 'modal',
                        'data-target' => 'modal-dialog'
                    ]
                ) ?>
                
                <?php if ($station->is_running): ?>
                    <?= Html::button(
                        '<i class="fa fa-play"></i> ' . Yii::t('common', 'Слушать радио'), 
                        [
                            'class' => 'button button-success radio-player-toggle',
                            'data-stream-url' => $station->getStreamUrl(),
                        ]
                    ) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($userTracksWait): ?>
            <div class="alert alert-info">
                <?= Yii::t('common', 'Ваши треки на проверке. Как только их проверят, они появятся в списке ниже.') ?>
            </div>
        <?php endif; ?>

        <!-- Now Playing Widget -->
        <div id="now-playing-widget" class="now-playing-block" style="display: none;">
            <div class="now-playing-header">
                <div class="now-playing-label">
                    <i class="fa fa-music"></i> <?= Yii::t('common', 'Сейчас играет:') ?>
                </div>
                <div class="now-playing-stats">
                    <span class="listeners-count">
                        <i class="fa fa-users"></i> <span id="listeners-count">0</span>
                    </span>
                </div>
            </div>
            <div id="current-track" class="now-playing-info">
                <!-- Загружается через JS -->
            </div>
            
            <div class="queue-section">
                <div class="queue-label"><?= Yii::t('common', 'Следующие треки:') ?></div>
                <div id="queue-list" class="queue-list">
                    <!-- Загружается через JS -->
                </div>
            </div>
        </div>

        <?php Pjax::begin([
            'id' => 'radio-tracks-pjax',
            'timeout' => 0,
            'enablePushState' => true,
            'scrollTo' => false,
        ]); ?>

        <!-- Фильтры -->
        <div class="radio-filters">
            <?php $form = ActiveForm::begin([
                'id' => 'radio-filter-form',
                'method' => 'get',
                'action' => ['station', 'id' => $station->id],
                'options' => ['data-pjax' => 1],
            ]); ?>

            <div class="filter-group">
                <?= $form->field($searchModel, 'title')
                    ->textInput([
                        'placeholder' => Yii::t('common', 'Название трека'),
                        'onchange' => 'this.form.submit()',
                    ])->label(false) ?>

                <?= $form->field($searchModel, 'artist')
                    ->textInput([
                        'placeholder' => Yii::t('common', 'Исполнитель'),
                        'onchange' => 'this.form.submit()',
                    ])->label(false) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>

        <?php
        $userLikes = \common\models\radio\RadioTrackLike::find()
            ->select('DISTINCT(radio_track_id)')
            ->andWhere(['user_id' => Yii::$app->user->id])
            ->createCommand()
            ->queryColumn();
        ?>

        <div class="radio-tracks-list" id="tracks-list">
            <?= ListView::widget([
                'dataProvider' => $dataProvider,
                'itemView' => '_track_item',
                'viewParams' => ['userLikes' => $userLikes, 'station' => $station],
                'options' => ['id' => 'tracks-list-wrapper', 'class' => 'listview-wrapper'],
                'summary' => false,
                'layout' => '<div class="radio-tracks-grid">{items}</div>{pager}',
                'pager' => [
                    'options' => ['class' => 'pagination'],
                    'linkOptions' => ['data-pjax' => 1],
                    'maxButtonCount' => 0,
                    'nextPageLabel' => Yii::t('common', 'Показать ещё'),
                    'prevPageLabel' => false,
                ],
            ]); ?>
        </div>

        <?php Pjax::end(); ?>
    </div>
</div>

<!-- Audio Player -->
<div id="radio-player" class="radio-player" style="display: none;">
    <div class="player-content">
        <div class="player-info">
            <i class="fa fa-radio"></i>
            <span class="player-station"><?= Html::encode($station->name) ?></span>
        </div>
        <audio id="radio-audio" preload="none"></audio>
        <div class="player-controls">
            <button class="player-btn play-btn" id="play-btn">
                <i class="fa fa-play"></i>
            </button>
            <button class="player-btn pause-btn" id="pause-btn" style="display: none;">
                <i class="fa fa-pause"></i>
            </button>
            <button class="player-btn close-btn" id="close-player-btn">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
$(document).on('click', '.radio-player-toggle', function() {
    var streamUrl = $(this).data('stream-url');
    var player = $('#radio-player');
    var audio = $('#radio-audio')[0];
    
    player.show();
    audio.src = streamUrl;
    audio.load();
    audio.play();
    $('#play-btn').hide();
    $('#pause-btn').show();
});

$(document).on('click', '#play-btn', function() {
    $('#radio-audio')[0].play();
    $(this).hide();
    $('#pause-btn').show();
});

$(document).on('click', '#pause-btn', function() {
    $('#radio-audio')[0].pause();
    $(this).hide();
    $('#play-btn').show();
});

$(document).on('click', '#close-player-btn', function() {
    $('#radio-audio')[0].pause();
    $('#radio-player').hide();
});

// Like functionality
$(document).on('click', '.track-like-btn', function(e) {
    e.preventDefault();
    var btn = $(this);
    var trackId = btn.data('track-id');
    var isGuest = btn.data('guest');
    
    if (isGuest) {
        alert('Авторизуйтесь для лайка треков');
        return;
    }
    
    $.ajax({
        url: '/radio/like',
        method: 'POST',
        data: {
            id: trackId,
            _csrf: yii.getCsrfToken()
        },
        success: function(response) {
            if (response.success) {
                btn.toggleClass('active');
                btn.find('.like-count').text(response.likes);
            }
        }
    });
});

// Show likes on hover
$(document).on('mouseenter', '.track-like-btn', function() {
    var btn = $(this);
    var trackId = btn.data('track-id');
    var tooltip = btn.find('.likes-tooltip');
    
    // Удаляем старый tooltip
    tooltip.remove();
    
    // Если нет лайков, не показываем
    var likesCount = parseInt(btn.find('.like-count').text());
    if (likesCount === 0) {
        return;
    }
    
    // Показываем загрузку
    tooltip = $('<div class="likes-tooltip"><div class="tooltip-loading">Загрузка...</div></div>');
    btn.append(tooltip);
    
    // Загружаем список лайков
    $.ajax({
        url: '/radio/get-likes',
        method: 'GET',
        data: { id: trackId },
        success: function(response) {
            if (response.users && response.users.length > 0) {
                var html = '<div class="tooltip-title">Лайки:</div>';
                response.users.forEach(function(user) {
                    html += `
                        <div class="tooltip-user">
                            <img src="\${user.avatar}" width="24" height="24">
                            <span>\${user.username}</span>
                        </div>
                    `;
                });
                tooltip.html(html);
            } else {
                tooltip.remove();
            }
        },
        error: function() {
            tooltip.remove();
        }
    });
});

// Hide tooltip on mouse leave
$(document).on('mouseleave', '.track-like-btn', function() {
    $(this).find('.likes-tooltip').remove();
});

// Now Playing Widget - update every 5 seconds
var stationId = {$station->id};

function updateNowPlaying() {
    $.ajax({
        url: '/radio/now-playing',
        method: 'GET',
        data: { id: stationId },
        success: function(data) {
            if (!data.station.is_running) {
                $('#now-playing-widget').hide();
                return;
            }
            
            $('#now-playing-widget').show();
            $('#listeners-count').text(data.station.listeners_count);
            
            // Update current track
            if (data.current) {
                var currentHtml = `
                    <div class="track-info-row">
                        <div class="track-main">
                            <div class="track-title">\${data.current.title}</div>
                            \${data.current.artist ? '<div class="track-artist">' + data.current.artist + '</div>' : ''}
                        </div>
                        <div class="track-meta">
                            <span class="track-duration"><i class="fa fa-clock"></i> \${data.current.duration}</span>
                            <span class="track-likes"><i class="fa fa-heart"></i> \${data.current.likes}</span>
                        </div>
                    </div>
                    <div class="track-user-small">
                        <img src="\${data.current.user.avatar}" width="24" height="24">
                        <span>\${data.current.user.username}</span>
                    </div>
                `;
                $('#current-track').html(currentHtml);
            } else {
                $('#current-track').html('<div class="no-track">Нет активного трека</div>');
            }
            
            // Update queue
            if (data.queue && data.queue.length > 0) {
                var queueHtml = '';
                data.queue.forEach(function(track) {
                    queueHtml += `
                        <div class="queue-item">
                            <div class="queue-item-title">\${track.title}</div>
                            <div class="queue-item-meta">
                                <img src="\${track.user.avatar}" width="20" height="20">
                                <span>\${track.user.username}</span>
                                <span class="queue-item-duration">\${track.duration}</span>
                            </div>
                        </div>
                    `;
                });
                $('#queue-list').html(queueHtml);
            } else {
                $('#queue-list').html('<div class="no-queue">Очередь пуста</div>');
            }
        }
    });
}

// Update now playing every 5 seconds
updateNowPlaying();
setInterval(updateNowPlaying, 5000);
JS
);
?>

<style>
.radio-station-header {
    margin-bottom: 24px;
}

.station-description {
    color: var(--text-secondary);
    margin: 12px 0;
}

.station-controls {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.station-controls .button {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.button-primary {
    background: var(--primary-colors-main);
    color: white;
}

.button-success {
    background: #28a745;
    color: white;
}

.now-playing-block {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.now-playing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.now-playing-label {
    font-size: 14px;
    opacity: 0.9;
}

.now-playing-stats {
    font-size: 14px;
    opacity: 0.9;
}

.track-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.track-main .track-title {
    font-size: 20px;
    font-weight: 600;
}

.track-main .track-artist {
    font-size: 16px;
    opacity: 0.9;
    margin-top: 4px;
}

.track-meta {
    display: flex;
    gap: 12px;
    font-size: 14px;
}

.track-user-small {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    opacity: 0.9;
}

.track-user-small img {
    border-radius: 50%;
}

.queue-section {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.queue-label {
    font-size: 13px;
    opacity: 0.8;
    margin-bottom: 12px;
}

.queue-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.queue-item {
    background: rgba(255,255,255,0.1);
    padding: 10px;
    border-radius: 8px;
    font-size: 13px;
}

.queue-item-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.queue-item-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0.9;
    font-size: 12px;
}

.queue-item-meta img {
    border-radius: 50%;
}

.queue-item-duration {
    margin-left: auto;
}

.no-track, .no-queue {
    opacity: 0.7;
    font-size: 14px;
    font-style: italic;
}

.radio-filters {
    margin-bottom: 24px;
}

.filter-group {
    display: flex;
    gap: 12px;
}

.filter-group .form-group {
    flex: 1;
    margin: 0;
}

.radio-tracks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

/* Radio Player */
.radio-player {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: var(--background-secondary);
    border: 1px solid var(--border-primary);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    z-index: 1000;
}

.player-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.player-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.player-controls {
    display: flex;
    gap: 8px;
}

.player-btn {
    background: var(--primary-colors-main);
    color: white;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.player-btn:hover {
    transform: scale(1.1);
}

.close-btn {
    background: #dc3545;
}

.pagination {
    display: flex;
    justify-content: center;
}

/* Queue notification */
.queue-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 10000;
    font-weight: 600;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Likes Tooltip */
.track-like-btn {
    position: relative;
}

.likes-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    margin-bottom: 8px;
    background: var(--background-secondary);
    border: 1px solid var(--border-primary);
    border-radius: 8px;
    padding: 8px;
    min-width: 200px;
    max-width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 1000;
}

.tooltip-title {
    font-weight: 600;
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--border-primary);
    font-size: 12px;
}

.tooltip-user {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px;
    font-size: 13px;
}

.tooltip-user img {
    border-radius: 50%;
}

.tooltip-loading {
    padding: 8px;
    text-align: center;
    color: var(--text-secondary);
    font-size: 13px;
}
</style>

