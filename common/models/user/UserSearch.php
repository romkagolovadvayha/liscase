<?php

namespace common\models\user;

use common\models\auth\AuthItem;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use common\components\base\query\DateQuery;

class UserSearch extends User
{

    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'email',
                    'status',
                    'ref_code',
                    'country_id',
                    'is_mailer',
                    'created_at',
                    'last_visit',
                    'userPhone',
                    'investorLevel',
                    'hasActiveLicense',
                ],
                'safe',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), []);
    }

    /**
     * @param array         $params
     * @param callable|null $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params, callable $filter = null)
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
            ])
            ->andFilterWhere(['LIKE', 't.email', $this->email]);

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

    public static function authRoles(): array
    {
        return ArrayHelper::map(AuthItem::find()->select('name')->asArray()->all(), 'name', 'name');
    }

    public static function authRolesNames(): array
    {
        return ArrayHelper::map(AuthItem::find()->select(['name', 'description'])->asArray()->all(), 'name', 'description');
    }
}
