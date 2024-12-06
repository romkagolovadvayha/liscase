<?php

return [
    'bootstrap'  => [
        'queueStats',
        'queueTop',
        'queueReport',
        'queueTeam',
        'queueTelegram',
//        'queueOpenAi',
//        'queueMidjourney',
    ],
    'components' => [
        'queueStats'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-stats',
            'ttr' => 1200,
        ],
        'queueTop'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-top',
            'ttr' => 1200,
        ],
        'queueReport'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-report',
            'ttr' => 1200,
        ],
        'queueTeam'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-team',
            'ttr' => 1200,
        ],
        'queueTelegram'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-telegram',
            'ttr' => 1200,
        ],
//        'queueOpenAi'                => [
//            'class'   => 'yii\queue\redis\Queue',
//            'redis'   => 'redis',
//            'channel' => 'queue-open-ai',
//            'ttr' => 1200,
//        ],
//        'queueMidjourney'                => [
//            'class'   => 'yii\queue\redis\Queue',
//            'redis'   => 'redis',
//            'channel' => 'queue-midjourney',
//            'ttr' => 1200,
//        ],
    ],
];