<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\components\queue\openAi\GenCommentJob;
use common\components\queue\openAi\GenPostJob;
use common\models\blog\Blog;
use common\models\blog\BlogRating;
use common\models\comment\Comment;
use common\models\user\User;
use DemonDogSL\translateManager\models\LanguageTranslate;
use yii\base\BaseObject;
use yii\console\Controller;
use Yii;
use yii\helpers\ArrayHelper;

class TestController extends Controller
{
    /**
     * test/clear-cache
     */
    public function actionClearCache()
    {
        Yii::$app->cache->flush();
    }

    /**
     * test/trim-database
     */
    public function actionTrimDatabase()
    {
        /** @var Blog[] $posts */
        $posts = Blog::find()->all();
        foreach ($posts as $post) {
            $post->keywords = trim($post->keywords);
            $post->description = trim($post->description);
            $post->content = trim($post->content);
            $post->save();
        }
    }

    /**
     * test/gen-rating
     */
    public function actionGenRating()
    {
        /** @var Blog[] $posts */
        $posts = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->all();
        foreach ($posts as $post) {
            $rand = rand(4, 10);
            /** @var User[] $users */
            $users = User::find()->limit($rand)->orderBy('rand()')->all();
            foreach ($users as $user) {
                $rating = new BlogRating();
                $rating->blog_id = $post->id;
                $rating->weight = 1;
                $rating->user_id = $user->id;
                $rating->save();
            }
        }
    }

    /**
     * test/gen-blog-user
     */
    public function actionGenBlogUser()
    {
        /** @var Blog[] $posts */
        $posts = Blog::find()->all();
        foreach ($posts as $post) {
            /** @var User $user */
            $user = User::find()->limit(1)->orderBy('rand()')->one();
            $post->user_id = $user->id;
            $post->save();
        }
    }

    /**
     * test/gen-comments
     */
    public function actionGenComments()
    {
        /** @var Blog[] $posts */
        $posts = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->orderBy(['created_at' => SORT_DESC])->all();
        foreach ($posts as $post) {
            $exist = Comment::find()->andWhere(['entityId' => $post->id])->exists();
            if ($exist) {
                continue;
            }
            Yii::$app->queueOpenAi->push(new GenCommentJob(['postId' => $post->id]));
        }
    }

    /**
     * test/gen-blog-content
     */
    public function actionGenBlogContent()
    {
        /** @var Blog[] $posts */
        $posts = Blog::find()->limit(100)->andWhere(['status' => Blog::STATUS_NOT_ACTIVE])->orderBy('RAND()')->all();
        foreach ($posts as $post) {
            Yii::$app->queueOpenAi->push(new GenPostJob(['postId' => $post->id]));
        }
    }

    /**
     * test/gen-views
     */
    public function actionGenViews()
    {
        /** @var Blog[] $posts */
        $posts = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->all();
        foreach ($posts as $post) {
            $post->views += rand(10, 30);
            $post->save();
        }
    }

    /**
     * test/load
     */
    public function actionLoad()
    {
        /** @var Blog $post */
        $post = Blog::findOne(139);
        $imageUrl = "https://cdn.discordapp.com/attachments/1150211599395737601/1162302667893248021/midjourney_gen_test_The_main_image_for_the_article_on_natural_s_25b0af01-c10d-43ef-ad00-0b0765bcda96.png?ex=653b71b6&is=6528fcb6&hm=8091c5ccdac0c867a0bc59cbc11a787774b01958a8f95c60aee61b5f44563326&";
        $key = "asddasd";
        echo $this->_load($imageUrl, $post, $key);
    }

    /**
     * @param $imageUrl
     * @param Blog $post
     *
     * @return string
     */
    private function _load($imageUrl, $post, $key) {
        $uploadDir = Yii::getAlias('@frontend/web');
        $md5 = md5($key);
        $fileUrl = "/uploads/posts/{$post->link_name}_$md5.png";
        $filePath = $uploadDir . $fileUrl;
        if (file_exists($filePath)) {
            return $fileUrl;
        }
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }

}
