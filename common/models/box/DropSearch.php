<?php

namespace common\models\box;

use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;
use Yii;

class DropSearch extends Drop
{
    public function rules(): array
    {
        return [
            [['id', 'name', 'image', 'status', 'created_at'], 'required'],
            [['status', 'type_id'], 'integer'],
            [['name', 'image'], 'string', 'max' => 255],
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
            'type_id'               => Yii::t('common', 'Тип'),
            'image'            => Yii::t('common', 'Изображение'),
            'price'              => Yii::t('common', 'Цена'),
            'status'              => Yii::t('common', 'Статус'),
            'created_at'          => Yii::t('common', 'Дата создания'),
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
                'status'     => $this->status,
                'type_id'     => $this->type_id,
                'price'   => $this->price,
            ])
            ->andFilterWhere(['LIKE', 'name', $this->name]);

        DateQuery::addDateCondition($query, $this, 'created_at');
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