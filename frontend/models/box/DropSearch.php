<?php

namespace frontend\models\box;

use common\components\base\query\DateQuery;
use common\models\box\Drop;
use yii\data\ActiveDataProvider;
use Yii;

class DropSearch extends Drop
{

    public $price_min;
    public $price_max;

    public function init()
    {
       parent::init();
       $this->price_min = 0;
       $this->price_max = Drop::getPriceMax();
    }

    public function rules(): array
    {
        return [
            [['price_min', 'price_max'], 'required'],
            [['type_id', 'price_min', 'price_max'], 'integer'],
            [['price_min', 'price_max'], 'integer', 'min' => 0],
            [['price_min', 'price_max', 'quality', 'name', 'type_id'], 'trim'],
            [['name', 'quality'], 'string', 'max' => 255],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'type_id'               => Yii::t('common', 'Тип предмета'),
            'image'            => Yii::t('common', 'Изображение'),
            'price'              => Yii::t('common', 'Цена'),
            'quality'              => Yii::t('common', 'Качество'),
            'status'              => Yii::t('common', 'Статус'),
            'created_at'          => Yii::t('common', 'Дата создания'),
            'price_min'          => Yii::t('common', 'Минимальная цена'),
            'price_max'          => Yii::t('common', 'Максимальная цена'),
        ];
    }

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, callable $filter = null)
    {
        $this->load($params);

        $query = self::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'id'       => $this->id,
                'type_id'     => $this->type_id,
                'status'     => Drop::STATUS_ACTIVE,
            ])
            ->andFilterWhere(['LIKE', 'quality', $this->quality])
            ->andFilterWhere([
                'OR',
                ['LIKE', 'name', $this->name],
                ['LIKE', 'eng_name', $this->name]
            ]);


        if (!empty($this->price_min)) {
            $query->andFilterWhere(['>=', 'price', $this->price_min]);
        }
        if (!empty($this->price_max)) {
            $query->andFilterWhere(['<=', 'price', $this->price_max]);
        }

//        DateQuery::addDateCondition($query, $this, 'created_at');
        DateQuery::addDateCondition($query, $this, 'price');

        return new ActiveDataProvider([
            'query'      => $query,
            'sort'       => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }
}