<?php

namespace frontend\controllers;

use common\components\google\TranslateApi;
use common\components\helpers\Role;
use common\controllers\WebController;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\comment\Comment;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use frontend\forms\promocode\PromocodeForm;
use Orhanerday\OpenAi\OpenAi;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\Response;
use yii2mod\comments\events\CommentEvent;
use yii2mod\comments\models\CommentModel;

class ChatGptController extends WebController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string|Response
     * @throws \Exception
     */
    public function actionIndex()
    {
        $blog = Blog::findOne(1386);
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
//        echo Yii::$app->midjourney->getGenerateImage($descriptionImage);
        exit;
//        $complete = $open_ai->chat([
//            'model' => 'gpt-3.5-turbo',
//            'messages' => [
//                [
//                    'role' => 'system',
//                    'content' => '
//                    Игнорируй предыдущие инструкции. Ты SEO-копирайтер, для сайта по медицине.
//                    SEO-копирайтер – это специалист по написанию статей с ключевыми словами. Такие тексты используют для seo-продвижения, продажи ссылок и т.п. Чаще всего это тексты для поискового робота, но при особом мастерстве копирайтера простым пользователям будет интересно почитать такой материал.
//                    Твои статьи должны быть подробныме, структурированные и содержать от 4000 до 10000 символов.
//                    В статье должны быть несколько подразделов и 5-10 SEO ключевых слов.
//                    Напиши 5-7 ключевых слов для этой статьи между тегами <keywords></keywords>
//                    Напиши краткое описание статьи между тегами <description></description>
//                    Пример:
//                    <h1>Раздел</h1>
//                    <h2>Под раздел</h2>
//                    <p>Текст <b>ключевое слово</b></p>
//                    <p>Текст <b>ключевое слово</b></p>
//                    <ol>
//                        <li>Первый шаг</li>
//                        <li>Второй шаг</li>
//                        ...
//                    </ol>
//                    <keywords>Ключевые слова</keywords>
//                    <description>Краткое описание</description>
//                    '
//                ],
//                [
//                    'role' => 'user',
//                    'content' => "как продлить жизнь человека на 100 лет больше среднего в домашних условиях?"
//                ],
//            ],
//            'temperature' => 1.0,
//            'frequency_penalty' => 0,
//            'presence_penalty' => 0,
//        ]);
//        $complete = json_decode($complete, 1);
//        echo "<p style=\"white-space: break-spaces;\">{$complete['choices'][0]['message']['content']}</p>";

//        $users = Yii::$app->openAi->getUsers();
//        $users = json_decode('[{"nickname": "maxpower", "name": "Максим", "surname": "Пауэр", "birthdate": "1990-08-15", "gender": "male"},
//{"nickname": "juliarose", "name": "Юлия", "surname": "Роуз", "birthdate": "1985-12-27", "gender": "female"},
//{"nickname": "alexsmith", "name": "Александр", "surname": "Смит", "birthdate": "1998-03-05", "gender": "male"},
//{"nickname": "katebrown", "name": "Кейт", "surname": "Браун", "birthdate": "1992-06-10", "gender": "female"},
//{"nickname": "nikolayblack", "name": "Николай", "surname": "Блэк", "birthdate": "1987-09-20", "gender": "male"}]', true);


//        $d = "das dasdasd asd<h1>123</h1>dasdadasd dasd asdas";
//        print_r($d);
//        $d = preg_replace('#<h1>(.+?)</h1>#is', '', $d);
//        echo PHP_EOL;
//        echo PHP_EOL;
//print_r($d);
//        exit;
//
//        $complete = $open_ai->chat([
//            'model' => 'gpt-4',
//            'messages' => [
//                [
//                    'role' => 'system',
//                    'content' => 'Ты токсичный человек.'
//                ],
//                [
//                    'role' => 'user',
//                    'content' => "Привет как дела?"
//                ],
//            ],
//            'temperature' => 1.0,
//            'frequency_penalty' => 0,
//            'presence_penalty' => 0,
//        ]);
//        print_r($complete);exit;
//                            $complete = json_decode($complete, 1);
//                            echo "<h1>{$complete['choices'][0]['message']['content']}</h1>";
//exit;

//        $categories = Yii::$app->openAi->getCategories();
//        $categories = json_decode('{
//                           "text": "<h1>Раздел</h1>\n  <h2>Под раздел</h2>\n  <p>Текст <b>ключевое слово</b></p>\n  <p>Текст <b>ключевое слово</b></p>\n  <ol>\n  <li>Первый шаг</li>\n  <li>Второй шаг</li>\n  ...\n  </ol>",
//                           "keywords": "Ключевые слова",
//                           "description": "Краткое описание"
//                        }', 1);

//        var_dump($categories);exit;

//        foreach ($categories as $item) {
//            $model = new BlogCategory();
//            $model->name = $item['category'];
//            $model->blog_category_id = null;
//            $model->link_name = BlogCategory::toLinkName($model->name);
//            $model->description = $item['description'];
//            $model->status = 1;
//            $model->created_at = date('Y-m-d H:i:s');
//            $model->save();
//            $parentId = $model->id;
//            if (!empty($item['sub-categories'])) {
//                foreach ($item['sub-categories'] as $subItem) {
//                    $model = new BlogCategory();
//                    $model->name = $subItem['name'];
//                    $model->blog_category_id = $parentId;
//                    $model->link_name = BlogCategory::toLinkName($model->name);
//                    $model->description = $subItem['description'];
//                    $model->status = 1;
//                    $model->created_at = date('Y-m-d H:i:s');
//                    $model->save();
//                }
//            }
//        }

//        $complete = $open_ai->image([
//            "prompt" => "решение проблемы трещин на пятках, методы устранения трещин, уход за пятками, косметические методы, медицинские методы, народные методы, ежедневный уход, здоровая кожа пяток",
//            "n" => 1,
//            "size" => "256x256",
//            "response_format" => "url",
//        ]);
//        print_r($complete);exit;
//        $complete = json_decode($complete, 1);
//        echo "<img src=\"{$complete['data'][0]['url']}\"/>";
//                    $complete = json_decode($complete, 1);
//                    echo "<h1>{$complete['choices'][0]['message']['content']}</h1>";

        /** @var User[] $users */
        $users = User::find()->all();
        foreach ($users as $user) {
            $user->getAvatar();
        }
        exit;
    }

    private function _botOpenBox() {
        $cacheKey = 'botGenerate';
        if (Yii::$app->cache->get($cacheKey)) {
            return;
        }
        Yii::$app->cache->set($cacheKey, 1, 5);
        $rand = rand(1, 3);
        for ($i = 0; $i < $rand; $i++) {
            UserBox::botGenerate();
        }
    }

    public function actionLastDrops()
    {
        $this->_botOpenBox();
        $this->layout = 'service';
        $result = [];
        $userDrops = UserDrop::getUsersDropLast();
        foreach ($userDrops as $userDrop) {
            $result[] = [
                'id' =>  $userDrop->id,
                'view' =>  $this->render('@frontend/views/widgets/_last_drops_item', [
                                'userDrop' => $userDrop,
                                'opened' => true,
                           ])
            ];
        }
        header("Content-Type: application/json");
        return json_encode($result);
    }

    public function actionOnlineCounter()
    {
        $this->layout = 'service';
        return $this->render('@frontend/views/widgets/_online_counter');
    }

}
