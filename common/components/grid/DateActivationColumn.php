<?php

namespace common\components\grid;

class DateActivationColumn extends DataColumn
{
    public $attribute      = 'date_activation';
    public $format         = 'datetime';
    public $contentOptions = ['class' => 'date-range-column'];
    public $filterOptions  = ['class' => 'date-range-column'];

    public function renderFilterCell()
    {
//        $this->filter = DateRangePicker::widget([
//            'model'     => $this->grid->filterModel,
//            'attribute' => $this->attribute,
//        ]);

        return parent::renderFilterCell();
    }
}