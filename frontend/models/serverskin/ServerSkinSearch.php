<?php
namespace frontend\models\serverskin;

use common\models\serverskin\ServerSkin;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

class ServerSkinSearch extends ServerSkin
{
    public function rules()
    {
        return [
            [['id', 'user_id', 'status'], 'integer'],
            [['name', 'created_at'], 'safe'],

            // <--- ключевое: приводим к массиву и валидируем элементы как int
            ['server_skin_category_id', 'filter', 'filter' => function ($v) {
                // приводим к массиву и выкидываем пустые/нулевые значения
                $arr = array_filter((array)$v, function($x){
                    return $x !== '' && $x !== null && $x !== '0' && $x !== 0;
                });
                // опционально — к int
                return array_map('intval', $arr);
            }],
            ['server_skin_category_id', 'each', 'rule' => ['integer']],
        ];
    }

    public function search($params)
    {
        $query = ServerSkin::find()->andWhere(['status' => ServerSkin::STATUS_ACTIVE]);

        $dataProvider = new ActiveDataProvider([
                                                   'query' => $query,
                                                   'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
                                                   'pagination' => ['pageSize' => 40],
                                               ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
                                   'id' => $this->id,
                                   'user_id' => $this->user_id,
                                   'status' => $this->status,
                                   'created_at' => $this->created_at,
                               ]);

        // <--- если выбран(ы) категории, фильтруем по IN
        if (!empty($this->server_skin_category_id)) {
            $query->andWhere(['in', 'server_skin_category_id', $this->server_skin_category_id]);
            // альтернативно: $query->andFilterWhere(['server_skin_category_id' => $this->server_skin_category_id]);
        }

        $query->andFilterWhere([
            'like',
            new Expression('CONVERT([[name]] USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
            $this->name,
        ]);

        return $dataProvider;
    }
}
