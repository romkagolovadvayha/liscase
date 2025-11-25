<?php

namespace frontend\modules\user;

use yii\base\Model;
use Yii;

/**
 * Our data model extends yii\base\Model class so we can get easy to use and yet
 * powerful Yii 2 validation mechanism.
 */
class SkinsSearch extends Model
{
    public $name;
    public $quality;
    public $price_min;
    public $price_max;
    public $sort;

    /**
     * Here we can define validation rules for each filtered column.
     * See http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     * for more information about validation.
     */
    public function rules()
    {
        return [
            [['name', 'sort'], 'string'],
            [['quality'], 'filter', 'filter' => function ($v) {
                // Приводим к массиву и выкидываем пустые значения
                if (empty($v)) {
                    return [];
                }
                if (!is_array($v)) {
                    return [$v];
                }
                return array_filter($v, function($x) {
                    return $x !== '' && $x !== null && $x !== 'all_types';
                });
            }],
            [['quality'], 'safe'],
            [['price_min', 'price_max'], 'integer', 'min' => 0],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'name' => \Yii::t('common', 'Название'),
            'quality' => \Yii::t('common', 'Качество'),
            'price_min' => \Yii::t('common', 'Цена от'),
            'price_max' => \Yii::t('common', 'Цена до'),
            'sort' => \Yii::t('common', 'Сортировка'),
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
    public function search($params, $type)
    {
        // Устанавливаем значение по умолчанию для сортировки, если не задано
        if (empty($this->sort)) {
            $this->sort = 'price_asc';
        }
        
        if ($this->load($params) && $this->validate()) {
            $this->_filtered = true;
            // Если после загрузки параметров sort пустой, устанавливаем по умолчанию
            if (empty($this->sort)) {
                $this->sort = 'price_asc';
            }
        }

        $data = $this->getData($type);
        
        // Определяем сортировку
        $sortConfig = $this->getSortConfig();

        return new \yii\data\ArrayDataProvider([
                                                   // ArrayDataProvider here takes the actual data source
                                                   'allModels' => $data,
                                                   'totalCount' => count($data),
                                                   'pagination' => [
                                                       'pageSize' => 91,
                                                   ],
                                                   'sort' => [
                                                       'attributes' => ['price', 'ru_name', 'popularity_7d'],
                                                       'defaultOrder' => $sortConfig,
                                                   ],
                                               ]);
    }
    
    protected function getSortConfig()
    {
        switch ($this->sort) {
            case 'price_asc':
                return ['price' => SORT_ASC];
            case 'price_desc':
                return ['price' => SORT_DESC];
            case 'name_asc':
                return ['ru_name' => SORT_ASC];
            case 'name_desc':
                return ['ru_name' => SORT_DESC];
            case 'popularity':
                return ['popularity_7d' => SORT_DESC];
            default:
                return ['price' => SORT_ASC];
        }
    }

    /**
     * Here we are preparing the data source and applying the filters
     * if _filtered property is set to true.
     */
    protected function getData($type)
    {
        if ($type == 'rust') {
            $data = \Yii::$app->rustTm->items();
        } else {
            $data = \Yii::$app->csGoMarket->items();
        }

        if ($this->_filtered) {
            $sName = !empty($this->name) ? mb_strtolower($this->name) : null;
            $quality = $this->quality;
            // Преобразуем quality в массив, если это строка
            if (!empty($quality) && !is_array($quality)) {
                $quality = [$quality];
            } elseif (empty($quality)) {
                $quality = [];
            }
            // Убираем 'all_types' из массива, если он есть
            $quality = array_filter($quality, function($q) {
                return $q !== 'all_types';
            });
            $priceMin = $this->price_min;
            $priceMax = $this->price_max;
            
            $data = array_filter($data, function ($value) use ($sName, $quality, $priceMin, $priceMax, $type) {
                $conditions = [true];
                
                // Фильтр по названию
                if ($sName) {
                    $conditions[] = strpos(mb_strtolower($value['name_search'] ?? ''), $sName) !== false;
                }
                
                // Фильтр по качеству/типу
                if (!empty($quality) && isset($value['ru_quality'])) {
                    // Для Rust и CS2 используем ru_quality
                    // Если quality - массив, проверяем вхождение
                    $conditions[] = in_array($value['ru_quality'], $quality);
                }
                
                // Фильтр по цене
                if ($priceMin !== null && $priceMin !== '') {
                    $conditions[] = isset($value['price']) && $value['price'] >= $priceMin;
                }
                if ($priceMax !== null && $priceMax !== '') {
                    $conditions[] = isset($value['price']) && $value['price'] <= $priceMax;
                }
                
                return array_product($conditions);
            });
        }

        return $data;
    }
}