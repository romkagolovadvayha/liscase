<?php

namespace frontend\modules\user;

use yii\base\Model;

/**
 * Our data model extends yii\base\Model class so we can get easy to use and yet
 * powerful Yii 2 validation mechanism.
 */
class SkinsSearch extends Model
{
    public $name;

    /**
     * Here we can define validation rules for each filtered column.
     * See http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     * for more information about validation.
     */
    public function rules()
    {
        return [
            [['name'], 'string'],
            // our columns are just simple string, nothing fancy
        ];
    }

    /**
     * In this example we keep this special property to know if columns should be
     * filtered or not. See search() method below.
     */
    private $_filtered = false;

    /**
     * This method returns ArrayDataProvider.
     * Filtered and sorted if required.
     */
    public function search($params)
    {
        if ($this->load($params) && $this->validate()) {
            $this->_filtered = true;
        }

        $data = $this->getData();

        return new \yii\data\ArrayDataProvider([
                                                   // ArrayDataProvider here takes the actual data source
                                                   'allModels' => $data,
                                                   'totalCount' => count($data),
                                                   'pagination' => [
                                                       'pageSize' => 91,
                                                   ],
                                                   'sort' => [
                                                       // we want our columns to be sortable:
                                                       'attributes' => ['diff', 'price', 'popularity_7d'],
                                                       'defaultOrder' => ['price' => SORT_ASC],
                                                   ],
                                               ]);
    }

    /**
     * Here we are preparing the data source and applying the filters
     * if _filtered property is set to true.
     */
    protected function getData()
    {
        $data = \Yii::$app->rustTm->items();

        if ($this->_filtered) {
            $sName = mb_strtolower($this->name);
            $data = array_filter($data, function ($value) use ($sName) {
                $conditions = [true];
                if (!empty($this->name)) {
                    $conditions[] = strpos(mb_strtolower($value['name']), $sName) !== false;
                }
                return array_product($conditions);
            });
        }

        return $data;
    }
}