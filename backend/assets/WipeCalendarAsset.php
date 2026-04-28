<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * FullCalendar 6 (CDN) + страница календаря вайпов.
 */
class WipeCalendarAsset extends AssetBundle
{
    public $sourcePath = '@backend/assets/sources';

    public $css = [
        'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/main.min.css',
        'css/wipe-calendar.css',
    ];

    public $js = [
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js',
        'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales-all.global.min.js',
        'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.11/index.global.min.js',
        'https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.11/index.global.min.js',
        'js/wipe-calendar.js',
    ];

    public $depends = [
        AppAsset::class,
    ];
}
