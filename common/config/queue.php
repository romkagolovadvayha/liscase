<?php

return [
    'bootstrap'  => [
        'queueOpenAi',
        'queueMidjourney',
    ],
    'components' => [
        'queueOpenAi'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-open-ai',
            'ttr' => 1200,
        ],
        'queueMidjourney'                => [
            'class'   => 'yii\queue\redis\Queue',
            'redis'   => 'redis',
            'channel' => 'queue-midjourney',
            'ttr' => 1200,
        ],
    ],
];