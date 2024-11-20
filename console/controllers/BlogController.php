<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\blog\BlogImage;
use common\models\box\Category;
use common\models\comment\Comment;
use common\models\user\User;
use yii\base\BaseObject;
use yii\console\Controller;
use Yii;
use yii\web\UploadedFile;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;

class BlogController extends Controller
{
    /**
     * blog/replace
     *
     * @throws \Exception
     */
    public function actionReplace()
    {
        /** @var Blog[] $items */
        $items = Blog::find()->all();
        $count = 0;
        foreach ($items as $item) {
//            if (!empty($item->description)) {
//                continue;
//            }
            try {
                $updated = false;
//                if (!empty($item->content)) {
//                    $description = Yii::$app->openAi->getPost($item->name, ' Сейчас содержание статьи такое: "' . $item->content . '". Ты должен переписать статью более удобно для читателя.', $item->blogCategory->name, $item->blogCategory->description);
//                } else {
//                    $description = Yii::$app->openAi->getPost($item->name, '', $item->blogCategory->name, $item->blogCategory->description);
//                }
//                preg_match_all('#<title>(.+?)</title>#is', $description, $title);
//                preg_match_all('#<text>(.+?)</text>#is', $description, $text);
//                preg_match_all('#<keywords>(.+?)</keywords>#is', $description, $keywords);
//                preg_match_all('#<descriptionShort>(.+?)</descriptionShort>#is', $description, $descriptionShort);
//
//                if (!empty($descriptionShort) && !empty($descriptionShort[1]) && !empty($descriptionShort[1][0])) {
//                    $item->name = $title[1][0];
//                    if (mb_strlen($item->content) < 1000) {
//                        $item->content = $text[1][0];
//                    }
//                    $item->keywords = $keywords[1][0];
//                    $item->description = $descriptionShort[1][0];
//                    $item->link_name = BlogCategory::toLinkName($item->name);
//                    $updated = true;
//                }

                if (strpos($item->content, '<br') === false && strpos($item->content, "\n") !== false) {
                    $item->content = str_replace("\n", "<br/>", $item->content);
                    $updated = true;
                }
                if (trim($item->name) == '||@????||' && !empty($item->content)) {
                    $item->name = Yii::$app->openAi->getTitle($item->content);
                    if (!empty($item->name['title'])) {
                        $item->name = $item->name['title'];
                    } elseif (!empty($item->name[0]) && !empty($item->name[0]['title'])) {
                        $item->name = $item->name[0]['title'];
                    }
                    $updated = true;
                }
                if (empty($item->link_name)) {
                    $item->delete();
                    continue;
                    //$item->link_name = BlogCategory::toLinkName($item->name);
                    //$updated = true;
                }
                if (strpos($item->content, '||@????||') !== false) {
                    $item->content = str_replace(['<br><br>||@????||', '<br>||@????||', '||@????||'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '????') !== false) {
                    $item->content = str_replace(['????'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->name, '||@????||') !== false) {
                    $item->name = str_replace(['||@????||'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, '@????') !== false) {
                    $item->name = str_replace(['@????'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, '|| ||') !== false) {
                    $item->name = str_replace(['|| ||'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, '||||') !== false) {
                    $item->name = str_replace(['||||'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, '??') !== false) {
                    $item->name = str_replace(['??'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, ':arrowdown:') !== false) {
                    $item->name = str_replace([':arrowdown:'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, ':Gunnn:') !== false) {
                    $item->name = str_replace([':Gunnn:'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, ':RUSTRUlogogif:') !== false) {
                    $item->name = str_replace([':RUSTRUlogogif:'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->content, ':CirclyMiniRUSTRU:') !== false) {
                    $item->content = str_replace([':CirclyMiniRUSTRU:'], '✅', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '||') !== false) {
                    $item->content = str_replace(['||'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, 'tram') !== false) {
                    $item->content = str_replace(['tram'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->name, '.') !== false) {
                    $arrName = explode('.', $item->name);
                    $name = $arrName[0];
                    if (!empty(trim($name)) && mb_strlen(trim($name)) > 20) {
                        $item->name = trim($name);
                        $updated = true;
                    }
                }
                if (strpos($item->content, 'Rust Mobile') !== false) {
                    $item->blog_category_id = 6;
                    $updated = true;
                }
                if (strpos($item->content, ':logo:') !== false) {
                    $item->content = str_replace([':logo:'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, 'everyone') !== false) {
                    $item->content = str_replace(['everyone'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->name, ':logo:') !== false) {
                    $item->name = str_replace([':logo:'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->name, 'everyone') !== false) {
                    $item->name = str_replace(['everyone'], '', $item->name);
                    $updated = true;
                }
                if (strpos($item->content, ':RUSTRUlogogif:') !== false) {
                    $item->content = str_replace([':RUSTRUlogogif:'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '<br/><br/><br/>') !== false) {
                    $item->content = str_replace(['<br/><br/><br/>'], '<br/><br/>', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '??@????') !== false) {
                    $item->content = str_replace(['<br/>??@????', '<br>??@????', '??@????'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '@????') !== false) {
                    $item->content = str_replace(['<br/>@????', '@????'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '||@???? ||') !== false) {
                    $item->content = str_replace(['<br><br>||@???? ||', '<br>||@???? ||', '||@???? ||'], '', $item->content);
                    $updated = true;
                }
                if (strpos($item->content, '[Источник](https://discord.gg/rust-ru)') !== false) {
                    $item->content = str_replace(['[Источник](https://discord.gg/rust-ru)'], '', $item->content);
                    $updated = true;
                }
                if (substr($item->content, -5) == '<br/>') {
                    $item->content = substr($item->content, 0,-5);
                    $updated = true;
                }
                if (substr($item->content, -11) == ':arrowredR:') {
                    $item->content = substr($item->content, 0,-11);
                    $updated = true;
                }
                if (substr($item->content, 0, 5) == '<br/>') {
                    $item->content = substr($item->content, 5);
                    $updated = true;
                }
                if (substr($item->content, -5) == '<br/>') {
                    $item->content = substr($item->content, 0,-5);
                    $updated = true;
                }
                if (substr($item->content, -5) == '<br/>') {
                    $item->content = substr($item->content, 0,-5);
                    $updated = true;
                }
                if ($updated) {
                    $item->content = trim($item->content);
                    $item->save(false);
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
                sleep(5);
            }
        }
    }

    /**
     * blog/sync2
     *
     * @throws \Exception
     */
    public function actionSync2()
    {
        $news = $this->getNewsRustRu();

        foreach ($news as $item) {
            $date = new \DateTime($item['date']);
            /** @var Blog $_item */
            $_item = Blog::find()->andWhere(['created_at' => $date->format('Y-m-d H:i:s')])->one();
            if (empty($_item)) {
                continue;
            }
            $_item->news_id = trim($item['newsId']);
            $_item->save();
        }
    }

    /**
     * blog/comments
     */
    public function actionComments()
    {
        $news = $this->getNewsRustRu(3000);
        $count = 0;
        foreach ($news as $item) {
            try {
                /** @var Blog $_item */
                $_item = Blog::find()->andWhere(['news_id' => trim($item['newsId'])])->one();
                if (empty($_item)) {
                    continue;
                }
                if (!empty($item['comments'])) {
                    foreach ($item['comments'] as $comment) {
                        if (empty($comment['steamId'])) {
                            continue;
                        }
                        if (empty(trim($comment['text']))) {
                            continue;
                        }
                        if (Comment::find()
                                   ->andWhere(['content' => trim($comment['text'])])
                                   ->andWhere(['url' => $_item->getUrl()])
                                   ->exists()) {
                            continue;
                        }
                        usleep(300);
                        $date = new \DateTime($comment['date']);
                        $user = User::findBySteamId($comment['steamId']);
                        $model = new Comment();
                        $model->entity = hash('crc32', get_class($_item));
                        $model->entityId = $_item->id;
                        $model->content = trim($comment['text']);
                        $model->parentId = null;
                        $model->level = 1;
                        $model->url = $_item->getUrl();
                        $model->status = 1;
                        $model->createdBy = $user->id;
                        $model->updatedBy = $user->id;
                        $model->createdAt = $date->getTimestamp();
                        $model->updatedAt = $date->getTimestamp();
                        $model->save();
                    }
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
        echo $count;
    }

    /**
     * blog/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        $news = $this->getNewsRustRu();
        $cats = BlogCategory::find()
                    ->indexBy('link_name')
                    ->all();

        foreach ($news as $item) {
            $date = new \DateTime($item['date']);
            if (Blog::find()->andWhere(['news_id' => trim($item['newsId'])])->exists()) {
                continue;
            }
            $title = $item['title'];
            $description = $item['description'];
            $attatchments = $item['attatchments'];
            $title = trim(str_replace([':LogoRUSTRU:'], '', $title));
            $title = \Normalizer::normalize($title, \Normalizer::NFKC);
            $description = trim(str_replace([':LogoRUSTRU:', '||@????||'], '', $description));
            $description = trim(str_replace("\n", '<br/>', $description));
            $description = \Normalizer::normalize($description, \Normalizer::NFKC);
            $categoryId = $cats['update']->id;
            if (strpos(mb_strtolower($title), "twitch") !== false) {
                $categoryId = $cats['twitch-drops']->id;
            }
            if (strpos(mb_strtolower($title), "скин") !== false) {
                $categoryId = $cats['skins']->id;
            }

            $dbTransaction = Yii::$app->db->beginTransaction();
            try {
                $model = new Blog();
                $model->status = Blog::STATUS_ACTIVE;
                $model->user_id = 509;
                $model->name = $title;
                $model->content = $description;
                $model->views = rand(200,424);
                $model->news_id = trim($item['newsId']);
                $model->blog_category_id = $categoryId;
                $model->created_at = $date->format('Y-m-d H:i:s');

                if (strpos($model->content, '[Источник](https://discord.gg/rust-ru)') !== false) {
                    $model->content = str_replace(['[Источник](https://discord.gg/rust-ru)'], '', $model->content);
                }

                if (strpos($model->content, '<br') === false && strpos($model->content, "\n") !== false) {
                    $model->content = str_replace("\n", "<br/>", $model->content);
                }

                if (!empty($model->content)) {
                    $description = Yii::$app->openAi->getPost($model->name, ' Сейчас содержание статьи такое: "' . $model->content . '". Ты должен переписать статью более удобно для читателя.', $model->blogCategory->name, $model->blogCategory->description);
                } else {
                    $description = Yii::$app->openAi->getPost($model->name, '', $model->blogCategory->name, $model->blogCategory->description);
                }
                preg_match_all('#<title>(.+?)</title>#is', $description, $title);
                preg_match_all('#<text>(.+?)</text>#is', $description, $text);
                preg_match_all('#<keywords>(.+?)</keywords>#is', $description, $keywords);
                preg_match_all('#<descriptionShort>(.+?)</descriptionShort>#is', $description, $descriptionShort);

                $model->name = $title[1][0];
                $model->content = $text[1][0];
                $model->keywords = $keywords[1][0];
                $model->description = $descriptionShort[1][0];
                $model->link_name = BlogCategory::toLinkName($model->name);

                if (strpos($model->content, 'Rust Mobile') !== false) {
                    $model->blog_category_id = 6;
                }

                $model->save(false);

                $loadFile = false;
                if (!empty($attatchments)) {
                    try {
                        foreach ($attatchments as $i => $attatchment) {
                            $file = file_get_contents($attatchment['url']);
                            if (!empty($file)) {
                                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                $mimeType = $finfo->buffer($file);
                                $exp = $this->mime2ext($mimeType);
                                $newFileName = $model->id . "_" . md5(time() + $i) . ".{$exp}";
                                Yii::$app->s3Api->uploadFile('blog/' . $newFileName, $file);
                                $bimage = new BlogImage();
                                $bimage->blog_id = $model->id;
                                $bimage->link = $newFileName;
                                $bimage->created_at = date('Y-m-d H:i:s');
                                $bimage->save(false);
                                $loadFile = true;
                            } else {
                                break;
                            }
                        }
                    } catch (\Exception $ex) {
                        echo $ex->getMessage() . PHP_EOL;
                    }
                }
                if ($loadFile || empty($attatchments)) {
                    $dbTransaction->commit();
                } else {
                    $dbTransaction->rollBack();
                }
            } catch (\Exception $e) {
                $dbTransaction->rollBack();
                throw new \Exception(Yii::t('common', $e->getMessage()));
            }
            break;
        }
    }

    private function getNewsRustRu($limit = 10) {
        $cacheKey = 'steam_getNewsRustRu_' . $limit;
        if (Yii::$app->cache->get($cacheKey)) {
            //return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "http://94.23.218.161:5000/api/v2/News?limit=" . $limit;
        $curl = clone Yii::$app->curl;
        $curl->setHeader('apiKey', 'RustRu');
        $response = json_decode($curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 3*60*60);
        return  $response;
    }

    private function mime2ext($mime) {
        $mime_map = [
            'application/x-compressed'                                                  => '7zip',
            'video/x-f4v'                                                               => 'f4v',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/png'                                                                => 'png',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'image/svg+xml'                                                             => 'svg',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
        ];

        return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
    }
}
