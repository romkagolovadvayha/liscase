<?php

use common\models\servers\Servers;
use yii\web\View;

/** @var View $this */

echo \common\models\servers\Servers::find()
                                         ->cache(10)
                                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                                         ->sum('players');