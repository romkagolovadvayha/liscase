<?php

namespace backend\models\avatar;

use common\models\avatar\AvatarFrame;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class AvatarFrameSearch extends AvatarFrame
{
    public function rules()
    {
        return [
            [['id', 'is_active', 'sort'], 'integer'],
            [['name', 'image_key'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = AvatarFrame::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['sort' => SORT_ASC, 'id' => SORT_ASC],
            ],
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['is_active' => $this->is_active]);
        $query->andFilterWhere(['sort' => $this->sort]);
        $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['like', 'image_key', $this->image_key]);

        return $dataProvider;
    }
}

