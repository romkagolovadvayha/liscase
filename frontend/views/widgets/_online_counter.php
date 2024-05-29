<?php
use yii\web\View;

/** @var View $this */

echo \common\models\servers\Servers::find()
                                         ->cache(10)
                                         ->sum('players');