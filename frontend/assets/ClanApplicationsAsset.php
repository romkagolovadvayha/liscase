<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Asset bundle для системы заявок в кланы
 */
class ClanApplicationsAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    
    public $css = [
        'css/clan-applications.css',
    ];
    
    public $js = [
        'js/clan-applications.js',
        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
    ];
    
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
    ];
}
