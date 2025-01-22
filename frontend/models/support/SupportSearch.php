<?php

namespace frontend\models\support;

use common\components\helpers\Role;
use common\models\user\User;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\support\Support;

/**
 * SupportSearch represents the model behind the search form of `common\models\support\Support`.
 */
class SupportSearch extends Support
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'status'], 'integer'],
            [['created_at', 'server_tag'], 'safe'],
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
     * @param User $user
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($user, $params)
    {
        $query = Support::find();

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

        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
            $query->andWhere(['user_id' => $user->id]);
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'status' => $this->status,
            'server_tag' => $this->server_tag,
        ]);

        return $dataProvider;
    }
}
