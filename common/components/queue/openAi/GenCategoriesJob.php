<?php

namespace common\components\queue\openAi;

use common\models\blog\BlogCategory;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class GenCategoriesJob extends BaseObject implements JobInterface
{
    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $categories = Yii::$app->openAi->getCategories();
            foreach ($categories as $item) {
                $model = new BlogCategory();
                $model->name = trim($item['category']);
                $model->blog_category_id = null;
                $model->link_name = BlogCategory::toLinkName($model->name);
                $model->description = trim($item['description']);
                $model->keywords = trim($item['keywords']);
                $model->status = 1;
                $model->created_at = date('Y-m-d H:i:s');
                $model->save(false);
                $parentId = $model->id;
                if (!empty($item['sub-categories'])) {
                    foreach ($item['sub-categories'] as $subItem) {
                        $model = new BlogCategory();
                        $model->name = $subItem['name'];
                        $model->blog_category_id = $parentId;
                        $model->link_name = BlogCategory::toLinkName($model->name);
                        $model->description = $subItem['description'];
                        $model->keywords = $subItem['keywords'];
                        $model->status = 1;
                        $model->created_at = date('Y-m-d H:i:s');
                        $model->save(false);
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("GenCategoriesJobError: " . $e->getMessage(), 'error');
            Yii::$app->cache->set('actionGenerateError', $e->getMessage(), 4000);
//            Yii::$app->cache->delete('actionGenerate');
            Yii::$app->queueOpenAi->push(new GenCategoriesJob());
        }
        Yii::$app->cache->delete('actionGenerate');
        Yii::$app->cache->delete('actionGenerateError');
    }
}