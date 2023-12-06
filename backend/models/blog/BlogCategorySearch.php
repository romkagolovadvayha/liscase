<?php

namespace backend\models\blog;

use yii\base\BaseObject;
use yii\base\Model;
use yii\data\ArrayDataProvider;
use common\models\blog\BlogCategory;

/**
 * BlogCategorySearch represents the model behind the search form of `common\models\blog\BlogCategory`.
 */
class BlogCategorySearch extends BlogCategory
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [];
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
     *
     * @return ArrayDataProvider
     */
    public function search($params)
    {
        $query = BlogCategory::find();

        $this->load($params);

        $query->andWhere([
            'blog_category_id' => null,
        ]);

        $models = [];
        foreach ($query->all() as $model) {
            $models[] = $model;
            $subCategories = BlogCategory::find()->andWhere([
                'blog_category_id' => $model->id,
            ])->all();
            foreach ($subCategories as $subCategory) {
                $models[] = $subCategory;
            }
        }

        return new ArrayDataProvider([
            'allModels'  => $models,
            'pagination' => [
                'pageSize' => 100,
            ],
        ]);
    }
}
