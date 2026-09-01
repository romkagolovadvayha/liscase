<?php

namespace backend\components;

use yii\base\Model;
use yii\grid\DataColumn;
use yii\helpers\Inflector;

/**
 * Accessible defaults for Kartik grids used throughout the administration area.
 */
class AccessibleKartikGridView extends \kartik\grid\GridView
{
    public function init()
    {
        if (empty($this->tableOptions['aria-label']) && empty($this->tableOptions['aria-labelledby'])) {
            $title = trim((string) $this->getView()->title);
            $this->tableOptions['aria-label'] = 'Таблица: ' . ($title !== '' ? $title : 'Данные страницы');
        }

        parent::init();
    }

    protected function initColumns()
    {
        parent::initColumns();

        foreach ($this->columns as $column) {
            if (empty($column->headerOptions['scope'])) {
                $column->headerOptions['scope'] = 'col';
            }

            if (!$column instanceof DataColumn || !$this->filterModel instanceof Model || $column->filter === false) {
                continue;
            }

            $attribute = $column->filterAttribute ?: $column->attribute;
            if ($attribute === null
                || !empty($column->filterInputOptions['aria-label'])
                || !empty($column->filterInputOptions['aria-labelledby'])
            ) {
                continue;
            }

            $label = trim((string) $this->filterModel->getAttributeLabel($attribute));
            if ($label === '') {
                $label = Inflector::camel2words((string) $attribute);
            }
            $column->filterInputOptions['aria-label'] = 'Фильтр: ' . $label;
        }
    }
}
