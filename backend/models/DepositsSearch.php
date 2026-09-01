<?php

namespace backend\models;

use common\models\invoice\Deposit;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use common\components\base\query\DateQuery;

class DepositsSearch extends Deposit
{

    public $username;
    public $steam_id;

    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'username',
                    'steam_id',
                    'status',
                    'payment_type',
                    'payment_id',
                    'amount',
                    'created_at',
                ],
                'safe',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'username' => 'Ник',
            'steam_id' => 'Steam ID',
        ]);
    }

    /**
     * @param array         $params
     * @param callable|null $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params, ?callable $filter = null, $userIds = [])
    {
        $this->load($params);

        $query = self::find()
                     ->alias('t');

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->joinWith(['user u'])
            ->andFilterWhere([
                't.id'              => $this->id,
                't.status'          => $this->status,
                't.payment_type'    => $this->payment_type,
                't.amount'          => $this->amount,
            ])
            ->andFilterWhere(['LIKE', 'u.username', $this->username])
            ->andFilterWhere(['LIKE', 'u.steam_id', $this->steam_id])
            ->andFilterWhere(['LIKE', 't.payment_id', $this->payment_id]);

        DateQuery::addDateCondition($query, $this, 't.created_at');

        return $this->_getDataProvider($query);
    }

    /**
     * @param $query
     *
     * @return ActiveDataProvider
     */
    protected function _getDataProvider($query)
    {
        return new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ]);
    }
}
