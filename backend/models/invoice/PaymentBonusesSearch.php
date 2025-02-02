<?php

namespace backend\models\invoice;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\invoice\PaymentBonuses;

/**
 * PaymentBonusesSearch represents the model behind the search form of `common\models\invoice\PaymentBonuses`.
 */
class PaymentBonusesSearch extends PaymentBonuses
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'amount', 'bonus'], 'integer'],
            [['created_at'], 'safe'],
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
        $query = PaymentBonuses::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
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
            'amount' => $this->amount,
            'bonus' => $this->bonus,
            'created_at' => $this->created_at,
        ]);

        return $dataProvider;
    }
}
