<?php

namespace backend\models\blog;

use yii\base\Model;
use Yii;
use yii\data\ActiveDataProvider;
use common\models\blog\Blog;
use yii\helpers\ArrayHelper;

/**
 * BlogSearch represents the model behind the search form of `common\models\blog\Blog`.
 */
class BlogSearch extends Blog
{

    public $category_ids;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'blog_category_id', 'status'], 'integer'],
            [['name', 'description', 'link_name', 'created_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'views' => Yii::t('common', 'По просмотрам'),
            'created_at' => Yii::t('common', 'По дате добавления')
        ]);
    }


    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param callable|null $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params, callable $filter = null)
    {
        $query = BlogSearch::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort'  => [
                'defaultOrder' => ['created_at' => SORT_DESC],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'blog_category_id' => $this->blog_category_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ]);

        if (!empty($this->category_ids)) {
            $query->andFilterWhere(['IN', 'blog_category_id', $this->category_ids]);
        }

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'link_name', $this->link_name]);

        return $dataProvider;
    }
}
