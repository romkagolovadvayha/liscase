<?php

namespace backend\models\support;

use common\models\support\SupportSticker;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * SupportStickerSearch represents the model behind the search form of `common\models\support\SupportSticker`.
 */
class SupportStickerSearch extends SupportSticker
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'width', 'height', 'sort', 'status', 'created_at', 'updated_at'], 'integer'],
            [['code', 'name', 'file', 'type'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = SupportSticker::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['sort' => SORT_ASC, 'id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'width' => $this->width,
            'height' => $this->height,
            'sort' => $this->sort,
            'status' => $this->status,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);

        $query->andFilterWhere(['like', 'code', $this->code])
              ->andFilterWhere(['like', 'name', $this->name])
              ->andFilterWhere(['like', 'file', $this->file]);

        return $dataProvider;
    }
}









