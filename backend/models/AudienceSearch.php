<?php

namespace backend\models;

use common\models\user\User;
use common\models\user\UserLicense;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use common\components\base\query\DateQuery;

class AudienceSearch extends User
{

    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'username',
                    'status',
                    'created_at',
                    'ref_code',
                    'steam_id',
                ],
                'safe',
            ],
        ];
    }

    /**
     * @param array         $params
     * @param callable|null $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params, callable $filter = null, $userIds = [])
    {
        $this->load($params);

        $query = self::find()
                     ->alias('t');

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        if (!empty($this->ref_code)) {
            $refCodes = explode(',', $this->ref_code);
            foreach ($refCodes as &$refCode) {
                $refCode = trim($refCode);
            }
            unset($refCode);

            $query->andWhere(['IN', 't.ref_code', $refCodes]);
        }

        $query
            ->joinWith(['userProfile up'])
            ->andFilterWhere([
                't.id'              => $this->id,
                't.status'          => $this->status,
                't.steam_id'          => $this->steam_id,
                't.ref_code'          => $this->ref_code,
            ])
            ->andFilterWhere(['LIKE', 't.username', $this->username]);

        DateQuery::addDateCondition($query, $this, 't.created_at');

        $query->andWhere(['IN', 't.id', $userIds]);

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
