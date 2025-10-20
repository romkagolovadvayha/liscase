<?php
return yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/web.php',
    require __DIR__ . '/main-local.php'
);

