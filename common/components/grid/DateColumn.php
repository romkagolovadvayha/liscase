<?php

namespace common\components\grid;

use kartik\daterange\DateRangePicker;
use Yii;
use kartik\grid\DataColumn;
use yii\base\Model;

class DateColumn extends DataColumn
{
    public $attribute      = 'created_at';
    public $format         = 'datetime';
    public $contentOptions = ['class' => 'date-range-column'];
    public $filterOptions  = ['class' => 'date-range-column'];

    public function renderFilterCell()
    {
        $model = $this->grid->filterModel;
        $label = $model instanceof Model
            ? $model->getAttributeLabel($this->attribute)
            : Yii::t('common', 'Дата');
        $this->filter = DateRangePicker::widget([
            'model'     => $model,
            'attribute' => $this->attribute,
            'options' => [
                'aria-label' => Yii::t('common', 'Фильтр: {label}', ['label' => $label]),
                'autocomplete' => 'off',
            ],
        ]);

        return parent::renderFilterCell();
    }
}
