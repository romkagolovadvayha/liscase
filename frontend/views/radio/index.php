<?php

use common\models\radio\RadioStation;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var RadioStation[] $stations */

$this->title = Yii::t('common', 'Радиостанции') . ' - ' . Yii::$app->name;
$this->registerMetaTag([
    'name' => 'description',
    'content' => Yii::t('common', 'Слушайте музыку в прямом эфире на наших радиостанциях. Загружайте свои треки, ставьте лайки и управляйте плейлистом.')
]);

// Open Graph мета-теги
$this->registerMetaTag([
    'property' => 'og:title',
    'content' => $this->title
]);
$this->registerMetaTag([
    'property' => 'og:description',
    'content' => Yii::t('common', 'Слушайте музыку в прямом эфире на наших радиостанциях. Загружайте свои треки, ставьте лайки и управляйте плейлистом.')
]);
$this->registerMetaTag([
    'property' => 'og:type',
    'content' => 'website'
]);
$this->registerMetaTag([
    'property' => 'og:url',
    'content' => \yii\helpers\Url::to(['radio/index'], true)
]);
?>

<?= \frontend\widgets\Alert::widget() ?>

<div class="server_info_page">
    <div class="radio-stations">
        <h1><?= Html::encode($this->title) ?></h1>
        
        <div class="radio-stations_list">
            <?php foreach ($stations as $station): ?>
                <div class="radio-station_card">
                    <div class="radio-station_card_header">
                        <h2><?= Html::encode($station->name) ?></h2>
                        <?php if ($station->is_running): ?>
                            <span class="radio-station_status running">
                                <i class="fa fa-circle"></i> <?= Yii::t('common', 'В эфире') ?>
                            </span>
                        <?php else: ?>
                            <span class="radio-station_status stopped">
                                <i class="fa fa-circle"></i> <?= Yii::t('common', 'Не в эфире') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($station->description): ?>
                        <p class="radio-station_description"><?= Html::encode($station->description) ?></p>
                    <?php endif; ?>
                    
                    <div class="radio-station_stats">
                        <div class="stat">
                            <i class="fa fa-music"></i>
                            <?= $station->getApprovedTracks()->count() ?> <?= Yii::t('common', 'треков') ?>
                        </div>
                        <div class="stat">
                            <i class="fa fa-users"></i>
                            <?= $station->listeners_count ?> <?= Yii::t('common', 'слушателей') ?>
                        </div>
                    </div>

                    <?php if ($station->currentTrack): ?>
                        <div class="radio-station_now-playing">
                            <div class="now-playing_label"><?= Yii::t('common', 'Сейчас играет:') ?></div>
                            <div class="now-playing_track">
                                <i class="fa fa-music"></i>
                                <strong><?= Html::encode($station->currentTrack->title) ?></strong>
                                <?php if ($station->currentTrack->artist): ?>
                                    - <?= Html::encode($station->currentTrack->artist) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="radio-station_actions">
                        <?= Html::a(
                            '<span class="button__text"><i class="fa fa-list"></i> ' . Yii::t('common', 'Список треков') . '</span>',
                            ['radio/station', 'id' => $station->id], 
                            ['class' => 'button button-primary']
                        ) ?>
                        
                        <?php if ($station->is_running): ?>
                            <?= Html::button(
                                '<i class="fa fa-play"></i> ' . Yii::t('common', 'Слушать'), 
                                [
                                    'class' => 'button button-success radio-listen-btn',
                                    'data-station-id' => $station->id,
                                    'data-stream-url' => $station->getStreamUrl(),
                                ]
                            ) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$this->registerJs('
// Обработчик клика на кнопку "Слушать"
$(document).on("click", ".radio-listen-btn", function() {
    var streamUrl = $(this).data("stream-url");
    var stationId = $(this).data("station-id");
    
    // Проверяем существование плеера
    if ($("#radio-player").length === 0) {
        // Создаём плеер
        var playerHtml = `
            <div id="radio-player" style="display:none;">
                <div class="player-content">
                    <div class="player-info">
                        <div class="player-title">Радио</div>
                    </div>
                    <div class="player-controls">
                        <button id="play-btn" class="player-btn"><i class="fa fa-play"></i></button>
                        <button id="pause-btn" class="player-btn" style="display:none;"><i class="fa fa-pause"></i></button>
                        <button id="close-player-btn" class="player-btn close-btn"><i class="fa fa-times"></i></button>
                    </div>
                    <audio id="radio-audio" preload="auto" crossorigin="anonymous"></audio>
                </div>
            </div>
        `;
        $("body").append(playerHtml);
    }
    
    var audio = $("#radio-audio")[0];
    
    // Устанавливаем URL потока
    audio.src = streamUrl;
    
    // Показываем плеер
    $("#radio-player").fadeIn();
    $("#play-btn").hide();
    $("#pause-btn").show();
    
    // Принудительно загружаем поток
    audio.load();
    
    // Пытаемся начать воспроизведение сразу
    var playPromise = audio.play();
    
    // Обрабатываем ошибки воспроизведения
    if (playPromise !== undefined) {
        playPromise.catch(function(error) {
            console.log("Auto-play prevented, waiting for user interaction or canplay event");
        });
    }
    
    // Начинаем воспроизведение как только будет достаточно данных
    audio.addEventListener("canplay", function() {
        if (audio.paused) {
            audio.play().catch(function(error) {
                console.log("Play error:", error);
            });
        }
    }, { once: true });
    
    // Также пытаемся начать при canplaythrough (когда весь трек загружен)
    audio.addEventListener("canplaythrough", function() {
        if (audio.paused) {
            audio.play().catch(function(error) {
                console.log("Play error:", error);
            });
        }
    }, { once: true });
});

// Обработчики для плеера
$(document).on("click", "#play-btn", function() {
    $("#radio-audio")[0].play();
    $(this).hide();
    $("#pause-btn").show();
});

$(document).on("click", "#pause-btn", function() {
    $("#radio-audio")[0].pause();
    $(this).hide();
    $("#play-btn").show();
});

$(document).on("click", "#close-player-btn", function() {
    $("#radio-audio")[0].pause();
    $("#radio-player").fadeOut();
});

// Обновление статуса через PHP контроллер каждые 10 секунд
function updateStationsStatus() {
    $(".radio-station_card").each(function() {
        var card = $(this);
        var button = card.find(".button-primary");
        if (button.length === 0) return;
        
        var href = button.attr("href");
        if (!href) return;
        
        var match = href.match(/id=(\\d+)/);
        if (!match) return;
        
        var stationId = match[1];
        
        $.ajax({
            url: "/radio/station-status",
            method: "GET",
            data: { id: stationId },
            success: function(data) {
                if (data.is_running) {
                    // Обновить счетчик слушателей
                    card.find(".stat").filter(function() {
                        return $(this).text().indexOf("слушателей") > -1;
                    }).html("<i class=\\"fa fa-users\\"></i> " + data.listeners_count + " слушателей");
                    
                    // Обновить статус
                    card.find(".radio-station_status").removeClass("stopped").addClass("running")
                        .html("<i class=\\"fa fa-circle\\"></i> В эфире");
                } else {
                    card.find(".radio-station_status").removeClass("running").addClass("stopped")
                        .html("<i class=\\"fa fa-circle\\"></i> Не в эфире");
                }
            }
        });
    });
}

// Обновить сразу и каждые 10 секунд
updateStationsStatus();
setInterval(updateStationsStatus, 10000);
');
?>


<style>
.radio-stations_list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.radio-station_card {
    background: var(--background-secondary);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border-primary);
}

.radio-station_card_header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.radio-station_card_header h2 {
    margin: 0;
    font-size: 20px;
}

.radio-station_status {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.radio-station_status.running {
    background: #d4edda;
    color: #155724;
}

.radio-station_status.running i {
    color: #28a745;
    animation: pulse 2s infinite;
}

.radio-station_status.stopped {
    background: #f8d7da;
    color: #721c24;
}

.radio-station_status.stopped i {
    color: #dc3545;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.radio-station_description {
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.radio-station_stats {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    padding: 12px;
    background: var(--background-teritiary);
    border-radius: 8px;
}

.radio-station_stats .stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}

.radio-station_now-playing {
    background: var(--background-teritiary);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.now-playing_label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.now-playing_track {
    font-size: 14px;
}

.radio-station_actions {
    display: flex;
    gap: 12px;
}

.radio-station_actions .button {
    flex: 1;
    text-align: center;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 14px;
    font-weight: 600;
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

.button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

