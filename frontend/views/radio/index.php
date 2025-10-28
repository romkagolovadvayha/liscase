<?php

use common\models\radio\RadioStation;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var RadioStation[] $stations */

$this->title = Yii::t('common', 'Радиостанции');
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
                            '<i class="fa fa-list"></i> ' . Yii::t('common', 'Список треков'), 
                            ['station', 'id' => $station->id], 
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
// Обновление статуса через PHP контроллер каждые 10 секунд
$this->registerJs('
function updateStationsStatus() {
    $(".radio-station_card").each(function() {
        var card = $(this);
        var stationId = card.find(".button-primary").attr("href").match(/id=(\d+)/)[1];
        
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

