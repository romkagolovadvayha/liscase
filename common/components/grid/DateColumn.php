<?php

namespace common\components\grid;

use kartik\daterange\DateRangePicker;
use Yii;
use kartik\grid\DataColumn;

class DateColumn extends DataColumn
{
    public $attribute      = 'created_at';
    public $format         = 'datetime';
    public $contentOptions = ['class' => 'date-range-column'];
    public $filterOptions  = ['class' => 'date-range-column'];

    public function renderFilterCell()
    {
        $this->filter = DateRangePicker::widget([
            'model'     => $this->grid->filterModel,
            'attribute' => $this->attribute,
        ]);

        return parent::renderFilterCell();
    }
}