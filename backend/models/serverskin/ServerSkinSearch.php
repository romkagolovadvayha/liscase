<?php

namespace backend\models\serverskin;

use common\models\serverskin\ServerSkin;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * ServerSkinSearch represents the model behind the search form of `common\models\serverskin\ServerSkin`.
 */
class ServerSkinSearch extends ServerSkin
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'status', 'skin_id'], 'integer'],
            [['name', 'created_at'], 'safe'],
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
        $table = ServerSkin::tableName();
        $query = ServerSkin::find()->joinWith(['user']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'       => [
                'attributes' => [
                    'id' => [
                        'asc' => ["{$table}.id" => SORT_ASC],
                        'desc' => ["{$table}.id" => SORT_DESC],
                        'default' => SORT_DESC,
                    ],
                ],
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // Qualify columns: user join also has id, status, created_at.
        $query->andFilterWhere([
            "{$table}.id" => $this->id,
            "{$table}.user_id" => $this->user_id,
            "{$table}.status" => $this->status,
            "{$table}.skin_id" => $this->skin_id,
            "{$table}.created_at" => $this->created_at,
        ]);

        $query->andFilterWhere(['like', "{$table}.name", $this->name]);

        return $dataProvider;
    }
}
