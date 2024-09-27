<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\blog\BlogImage;
use common\models\box\Category;
use common\models\user\User;
use yii\base\BaseObject;
use yii\console\Controller;
use Yii;
use yii\web\UploadedFile;

class BlogController extends Controller
{
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
            if (Blog::find()->andWhere(['created_at' => $date->format('Y-m-d H:i:s')])->exists()) {
                continue;
            }
            $title = $item['title'];
            $description = $item['description'];
            $attatchments = $item['attatchments'];
            $title = trim(str_replace([':LogoRUSTRU:'], '', $title));
            $description = trim(str_replace([':LogoRUSTRU:'], '', $description));
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
                $model->blog_category_id = $categoryId;
                $model->created_at = $date->format('Y-m-d H:i:s');
                $model->save(false);

                $loadFile = false;
                if (!empty($attatchments)) {
                    try {
                        foreach ($attatchments as $attatchment) {
                            if (strpos(strtolower($attatchment['url']), ".jpg") === false
                                && strpos(strtolower($attatchment['url']), ".png") === false) {
                                continue;
                            }
                            if (!empty(file_get_contents($attatchment['url']))) {
                                $this->_loadImage(file_get_contents($attatchment['url']), $model->id);
                                $loadFile = true;
                            } else {
                                break;
                            }
                        }
                    } catch (\Exception $ex) {
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
    private function _loadImage($image, $boxId) {
        if (empty($image) || empty($image->tempName)) {
            return null;
        }
        echo 12;
        exit;
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileUrl = "/blog/" . $this->id . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($image->tempName));
        $image = new BlogImage();
        $image->link = $fileUrl;
        $image->blog_id = $boxId;
        $image->description = $this->name;
        $image->save();
        return $filePath;
    }

    private function getNewsRustRu() {
        $cacheKey = 'steam_getNewsRustRu';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "http://94.23.218.161:5000/api/v2/News?limit=3000";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 3*60*60);
    }

}
