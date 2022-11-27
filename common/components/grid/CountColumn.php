<?php

namespace common\components\grid;

use Yii;

class CountColumn extends DataColumn
{
    public $format = 'integer';
    public $contentOptions = ['class' => 'text-right'];
    public $headerOptions = ['class' => 'text-left'];
}