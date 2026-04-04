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
    public function search($params, $type, $page = 1, $pageSize = 24)
    {
        // Устанавливаем значение по умолчанию для сортировки, если не задано
        if (empty($this->sort)) {
            $this->sort = 'price_asc';
        }
        
        // Загружаем параметры из запроса
        $this->load($params);
        
        // Устанавливаем sort из параметров, если он передан
        if (isset($params['sort']) && !empty($params['sort'])) {
            $this->sort = $params['sort'];
        }
        
        // Валидируем модель
        if ($this->validate()) {
            // Устанавливаем флаг фильтрации, если есть хотя бы один фильтр
            $hasFilters = !empty($this->name) || 
                         !empty($this->quality) || 
                         ($this->price_min !== null && $this->price_min !== '') ||
                         ($this->price_max !== null && $this->price_max !== '');
            
            $this->_filtered = $hasFilters;
            
            // Если после загрузки параметров sort пустой, устанавливаем по умолчанию
            if (empty($this->sort)) {
                $this->sort = 'price_asc';
            }
        } else {
            // Если валидация не прошла, все равно применяем фильтры если они есть
            $hasFilters = !empty($this->name) || 
                         !empty($this->quality) || 
                         ($this->price_min !== null && $this->price_min !== '') ||
                         ($this->price_max !== null && $this->price_max !== '');
            $this->_filtered = $hasFilters;
        }

        $data = $this->getData($type);
        
        // Определяем сортировку
        $sortConfig = $this->getSortConfig();
        
        // Всегда применяем сортировку к данным (даже если нет фильтров)
        if (!empty($sortConfig)) {
            $sortField = key($sortConfig);
            $sortOrder = $sortConfig[$sortField];
            
            usort($data, function($a, $b) use ($sortField, $sortOrder) {
                $aVal = $a[$sortField] ?? null;
                $bVal = $b[$sortField] ?? null;
                
                // Обработка null значений
                if ($aVal === null && $bVal === null) return 0;
                if ($aVal === null) return ($sortOrder === SORT_ASC ? 1 : -1);
                if ($bVal === null) return ($sortOrder === SORT_ASC ? -1 : 1);
                
                // Сравнение
                if (is_numeric($aVal) && is_numeric($bVal)) {
                    $result = $aVal <=> $bVal;
                } else {
                    $result = strcmp((string)$aVal, (string)$bVal);
                }
                
                return $sortOrder === SORT_ASC ? $result : -$result;
            });
        }

        return new \yii\data\ArrayDataProvider([
                                                   // ArrayDataProvider here takes the actual data source
                                                   'allModels' => $data,
                                                   'totalCount' => count($data),
                                                   'pagination' => [
                                                       'page' => $page - 1, // Yii2 использует 0-based индексацию
                                                       'pageSize' => $pageSize,
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

        // Всегда применяем фильтры, если они заданы (даже если _filtered = false)
        // Это нужно для корректной работы поиска и других фильтров
        $hasFilters = !empty($this->name) || 
                     !empty($this->quality) || 
                     ($this->price_min !== null && $this->price_min !== '') ||
                     ($this->price_max !== null && $this->price_max !== '');
        
        if ($this->_filtered || $hasFilters) {
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

    /**
     * Минимальная и максимальная цена (поле price) по всему каталогу типа — для подсказок в UI.
     *
     * @param string $type rust|cs2
     * @return array{min: int, max: int}
     */
    public static function getCatalogPriceRange(string $type): array
    {
        if ($type === 'rust') {
            $data = \Yii::$app->rustTm->items();
        } else {
            $data = \Yii::$app->csGoMarket->items();
        }
        if (empty($data)) {
            return ['min' => 0, 'max' => 0];
        }
        $prices = [];
        foreach ($data as $row) {
            if (!isset($row['price']) || !is_numeric($row['price'])) {
                continue;
            }
            $prices[] = (float) $row['price'];
        }
        if ($prices === []) {
            return ['min' => 0, 'max' => 0];
        }

        return [
            'min' => (int) floor(min($prices)),
            'max' => (int) ceil(max($prices)),
        ];
    }
}
