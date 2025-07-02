<?php

namespace cabinet\components\chart;

use Yii;
use common\components\report\UserReport;

class ReferralChart extends UserReport
{
    public function __construct($userId = null)
    {
        parent::__construct(Yii::$app->user->id);
    }
}