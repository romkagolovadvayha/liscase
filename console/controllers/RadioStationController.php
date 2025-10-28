<?php

namespace console\controllers;

use common\models\radio\RadioStation;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console controller for managing radio stations
 */
class RadioStationController extends Controller
{
    /**
     * Update stream URL for a radio station
     * 
     * Usage: php yii radio-station/update-stream-url <id> <url>
     * Example: php yii radio-station/update-stream-url 1 http://example.com:8081
     */
    public function actionUpdateStreamUrl($id, $url)
    {
        $station = RadioStation::findOne($id);
        
        if (!$station) {
            echo "Radio station not found\n";
            return ExitCode::DATAERR;
        }
        
        $station->stream_url = $url;
        
        if ($station->save()) {
            echo "✅ Stream URL updated successfully\n";
            echo "   Station: {$station->name}\n";
            echo "   Old URL: " . ($station->getOldAttribute('stream_url') ?: 'localhost') . "\n";
            echo "   New URL: {$url}\n";
            echo "   Full Stream URL: {$station->getStreamUrl()}\n";
            return ExitCode::OK;
        } else {
            echo "❌ Failed to update stream URL\n";
            foreach ($station->errors as $error) {
                echo "   " . implode(', ', $error) . "\n";
            }
            return ExitCode::DATAERR;
        }
    }
    
    /**
     * Clear stream URL for a radio station (reset to localhost)
     * 
     * Usage: php yii radio-station/clear-stream-url <id>
     * Example: php yii radio-station/clear-stream-url 1
     */
    public function actionClearStreamUrl($id)
    {
        $station = RadioStation::findOne($id);
        
        if (!$station) {
            echo "Radio station not found\n";
            return ExitCode::DATAERR;
        }
        
        $oldUrl = $station->stream_url;
        $station->stream_url = null;
        
        if ($station->save()) {
            echo "✅ Stream URL cleared\n";
            echo "   Station: {$station->name}\n";
            echo "   Old custom URL: " . ($oldUrl ?: 'NULL') . "\n";
            echo "   Now using: {$station->getStreamUrl()}\n";
            return ExitCode::OK;
        } else {
            echo "❌ Failed to clear stream URL\n";
            foreach ($station->errors as $error) {
                echo "   " . implode(', ', $error) . "\n";
            }
            return ExitCode::DATAERR;
        }
    }
    
    /**
     * List all radio stations with their stream URLs
     * 
     * Usage: php yii radio-station/list
     */
    public function actionList()
    {
        $stations = RadioStation::find()->all();
        
        if (empty($stations)) {
            echo "No radio stations found\n";
            return ExitCode::OK;
        }
        
        echo "\n📻 Radio Stations:\n";
        echo str_repeat("=", 80) . "\n";
        
        foreach ($stations as $station) {
            echo "\nID: {$station->id}\n";
            echo "Name: {$station->name}\n";
            echo "Port: {$station->port}\n";
            echo "Stream URL (custom): " . ($station->stream_url ?: 'NULL') . "\n";
            echo "Stream URL (actual): {$station->getStreamUrl()}\n";
            echo "Status: " . ($station->is_running ? 'Running' : 'Stopped') . "\n";
            echo str_repeat("-", 80) . "\n";
        }
        
        return ExitCode::OK;
    }
}

