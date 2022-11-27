<?php

namespace common\components\grid;

use Yii;

class AmountColumn extends DataColumn
{
    public $format         = ['decimal', 2];
    public $contentOptions = ['class' => 'text-right'];
    public $headerOptions  = ['class' => 'text-left'];
}