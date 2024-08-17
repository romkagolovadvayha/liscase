<?php

return [
    'bootstrap'  => [
        'queueStats',
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