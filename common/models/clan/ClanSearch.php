<?php

namespace common\models\clan;

use common\components\base\query\DateQuery;
use common\models\clan\Clan;
use yii\data\ActiveDataProvider;
use Yii;

class ClanSearch extends Clan
{

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
                             ])
            ->andFilterWhere(['LIKE', 'title', $this->title]);

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