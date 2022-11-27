<?php

namespace common\components\grid;

use Yii;
use yii\helpers\ArrayHelper;
use common\components\helpers\Status;

class StatusColumn extends DataColumn
{
    public $attribute = 'status';
    public $statusList = false;

    public function renderFilterCell()
    {
        $this->filter = $this->statusList;

        return parent::renderFilterCell();
    }

    public function getDataCellValue($model, $key, $index)
    {
        $attribute = $this->attribute;

        return ArrayHelper::getValue($this->filter, $model->{$attribute});
    }
}