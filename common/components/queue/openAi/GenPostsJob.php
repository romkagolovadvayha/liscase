<?php

namespace common\components\queue\openAi;

use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class GenPostsJob extends BaseObject implements JobInterface
{
    public $categoryId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        /** @var BlogCategory $category */
        $category = BlogCategory::findOne($this->categoryId);
        try {
            try {
                $titles = Yii::$app->openAi->getTitles($category->name, $category->description);
            } catch (\Exception $e) {
                Yii::error("getTitlesError: " . $e->getMessage(), 'error');
                $titles = Yii::$app->openAi->getTitles($category->name, $category->description);
            }
            foreach ($titles as $item) {
                $model = new Blog();
                $model->name = trim($item['title']);
                $model->blog_category_id = $this->categoryId;
                $model->link_name = BlogCategory::toLinkName($model->name);
                $model->status = 0;
                $user = User::find()->limit(1)->orderBy('rand()')->one();
                $model->user_id = $user->id;
                $model->created_at = date('Y-m-d H:i:s');
                $model->save(false);
//                Yii::$app->queueOpenAi->push(new GenPostJob([
//                    'postId' => $model->id
//                ]));
            }
        } catch (\Exception $e) {
            Yii::error("GenPostsJobError: " . $e->getMessage(), 'error');
            Yii::$app->cache->set('actionGenerateError_Posts_' . $this->categoryId, $e->getMessage(), 4000);
//            Yii::$app->cache->delete('actionGenerate_Posts_' . $this->categoryId);
            Yii::$app->queueOpenAi->push(new GenPostsJob([
                'categoryId' => $this->categoryId,
            ]));
        }
        Yii::$app->cache->delete('actionGenerate_Posts_' . $this->categoryId);
    }
}