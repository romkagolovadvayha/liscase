<?php

namespace common\components\queue\openAi;

use common\components\google\TranslateApi;
use common\components\queue\midjourney\GenImageJob;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class GenPostJob extends BaseObject implements JobInterface
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
        /** @var Blog $post */
        $post = Blog::findOne($this->postId);
        if ($post->status == Blog::STATUS_ACTIVE) {
            return;
        }
        try {
            $structurePost = Yii::$app->openAi->getStructurePost($post->name, $post->blogCategory->description, $post->blogCategory->name);
            preg_match_all('#<structure>(.+?)</structure>#is', $structurePost, $structure);
            preg_match_all('#<keywords>(.+?)</keywords>#is', $structurePost, $keywords);
            preg_match_all('#<description>(.+?)</description>#is', $structurePost, $description);
            try {
                $data = Yii::$app->openAi->getPost($post->name, $structure[1][0], $post->blogCategory->description, $post->blogCategory->name);
            } catch (\Exception $e) {
                sleep(5);
                $data = Yii::$app->openAi->getPost($post->name, $structure[1][0]);
            }
            $text = preg_replace('#<h1>(.+?)</h1>#is', '', $data);

            preg_match_all('#<image>(.+?)</image>#is', $text, $images);
            foreach ($images[1] as $i => $imageDesc) {
                if ($i > 4) {
                    $text = str_replace("<image>$imageDesc</image>", '', $text);
                    continue;
                }
                $keyQueue = "QUEUE_WAIT_GEN_IMAGE_$i";
                $google = new TranslateApi();
                $imageDescEng = $google->translateText($imageDesc, 'en');
                Yii::$app->queueMidjourney->push(new GenImageJob([
                    'postId' => $this->postId,
                    'description' => $imageDescEng,
                    'key' => $keyQueue,
                ]));
                $text = str_replace("<image>$imageDesc</image>", $keyQueue, $text);
            }

            $post->description = trim($description[1][0]);
            $post->content = trim($text);
            $post->keywords = trim($keywords[1][0]);
            $post->status = 1;
            $post->created_at = date('Y-m-d H:i:s');
            $post->save();
        } catch (\Exception $e) {
            Yii::error("GenPostJobError: " . $e->getMessage(), 'error');
            Yii::$app->queueOpenAi->push(new GenPostJob([
                'postId' => $this->postId
            ]));
        }
        Yii::$app->cache->delete('actionGenerate_Post_' . $this->postId);
    }
}