<?php

namespace common\components\queue\openAi;

use common\models\blog\Blog;
use common\models\comment\Comment;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class GenCommentJob extends BaseObject implements JobInterface
{
    public $postId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $blog = Blog::findOne($this->postId);
        $comments = Yii::$app->openAi->getComments($blog->name, $blog->description, rand(1,5));
        foreach ($comments as $item) {
            /** @var User $user */
            $user = User::find()->limit(1)->orderBy('rand()')->one();
            $model = new Comment();
            $model->entity = hash('crc32', get_class($blog));
            $model->entityId = $blog->id;
            $model->content = $item['comment'];
            $model->parentId = null;
            $model->level = 1;
            $model->url = $blog->getUrl();
            $model->status = 1;
            $model->createdBy = $user->id;
            $model->updatedBy = $user->id;
            $model->createdAt = time();
            $model->updatedAt = time();
            $model->save();
        }
    }
}