<?php

namespace cabinet\components\chart;

use Yii;
use common\components\report\CountryReport;

class CountryChart extends CountryReport
{
    public function __construct($userId = null)
    {
        parent::__construct(Yii::$app->user->id);
    }
}