<?php

namespace frontend\models\maps;

use common\components\base\query\DateQuery;
use common\models\map\Map;
use yii\base\BaseObject;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * BuildingSearch represents the model behind the search form of `common\models\maps\Map`.
 */
class MapsSearch extends Map
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'votes', 'size'], 'integer'],
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
     * @param $params
     * @param $minSize
     * @param $maxSize
     *
     * @return ActiveDataProvider
     */
    public function search($params, $minSize, $maxSize, $serverId)
    {
        $query = MapsSearch::find()->limit(100);

        $dataProvider = new ActiveDataProvider([
                                                   'query' => $query,
                                                   'sort'       => [
                                                       'defaultOrder' => ['id' => SORT_DESC],
                                                   ],
//                                                   'pagination' => [
//                                                       'pageSize' => 30,
//                                                   ],
                                                   'pagination' => false,
                                               ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
                                   'id' => $this->id,
                                   'votes' => $this->votes,
                                   'size' => $this->size,
                                   'is_archive' => 0,
                               ]);
        $query->andFilterWhere(['>=', 'size', $minSize]);
        $query->andFilterWhere(['<=', 'size', $maxSize]);
        $query->andFilterWhere(['server_id' => $serverId]);

        return $dataProvider;
    }
}
