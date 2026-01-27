<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\components\web\Cookie;
use common\models\box\Box;
use common\models\box\BoxDrop;
use common\models\box\Category;
use common\models\box\Drop;
use common\models\box\DropDrop;
use common\models\box\DropImage;
use common\models\box\DropType;
use common\models\box\Sets;
use common\models\box\SetsDrop;
use common\models\profit\Profit;
use common\models\user\Auth;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use yii\base\BaseObject;
use yii\console\Controller;

class GamestoresController extends Controller
{
    /**
     * Парсит товары с gamestores
     * gamestores/product-parsing
     *
     * @throws \Exception
     */
    public function actionProductParsing()
    {
        $result = json_decode(file_get_contents(__DIR__ . "/../../drops.json"), 1)['data'];
        $products = $result['products'];
        $categories = $result['categories'];

        $cats = [];
        $types = [];
        $google = new TranslateApi();
        foreach ($categories as $category) {
            $categoryBD = Category::find()
                ->andWhere(['name' => $category['name']])
                ->one();
            if (!empty($categoryBD)) {
                $cats[$category['id']] = $categoryBD->id;
                continue;
            }
            $categoryTag = strtolower($google->translateText($category['name']));
            $lastId = Category::createRecord($category['name'], $categoryTag);
            $cats[$category['id']] = $lastId;
        }
        foreach ($categories as $category) {
            /** @var DropType $categoryBD */
            $categoryBD = DropType::find()
                ->andWhere(['name' => $category['name']])
                ->one();
            if (!empty($categoryBD)) {
                $types[$category['id']] = $categoryBD->id;
                continue;
            }
            $categoryTag = strtolower($google->translateText($category['name']));
            $id = DropType::createRecord($category['name'], $categoryTag);
            $types[$category['id']] = $id;
        }


        for ($i = count($products) - 1; $i >= 0; $i--) {
            $product = $products[$i];
            if ($product['type'] === 'item') {
                /** @var Drop $model */
                $model = Drop::find()
                    ->andWhere(['rust_id' => $product['data']['itemId']])
                    ->one();

                if (empty($model)) {
                    $model = new Drop();
                    $model->name = $product['name'];
                }
                $model->price = $product['price'];
                $model->rust_id = $product['data']['itemId'];
                if ($product['itemEnabled']) {
                    $model->market_status = Drop::MARKET_STATUS_ACTIVE;
                } else {
                    $model->market_status = Drop::MARKET_STATUS_NOT_ACTIVE;
                }
                $model->status = Drop::STATUS_ACTIVE;
                $model->count = $product['amount'];
                if (!empty($product['about'])) {
                    $model->description = $product['about'];
                }
                $model->discount = $product['discount'];
                $model->category_id = !empty($cats[$product['categoryId']]) ? $cats[$product['categoryId']] : NULL;
                $model->type_id = !empty($types[$product['categoryId']]) ? $types[$product['categoryId']] : NULL;
                $model->save();
                if (!empty($product['img'])) {
                    $image = DropImage::find()
                                      ->andWhere(['drop_id' => $model->id])
                                      ->one();
                    if (empty($image)) {
                        $this->_loadImageDrop($product['img'], $model->id);
                    }
                }
            }
            if ($product['type'] === 'command') {
                /** @var Drop $model */
                $model = Drop::find()
                             ->andWhere(['name' => $product['name']])
                             ->one();

                if (empty($model)) {
                    $model = new Drop();
                    $model->name = $product['name'];
                }
                $model->price = $product['price'];
                if ($product['itemEnabled']) {
                    $model->market_status = Drop::MARKET_STATUS_ACTIVE;
                } else {
                    $model->market_status = Drop::MARKET_STATUS_NOT_ACTIVE;
                }
                $model->status = Drop::STATUS_ACTIVE;
                $model->count = $product['amount'];
                if (!empty($product['about'])) {
                    $model->description = $product['about'];
                }
                if (!empty($product['data']) && !empty($product['data']['commands'])) {
                    $model->command = $product['data']['commands'];
                }
                $model->discount = $product['discount'];
                $model->category_id = !empty($cats[$product['categoryId']]) ? $cats[$product['categoryId']] : NULL;
                $model->type_id = !empty($types[$product['categoryId']]) ? $types[$product['categoryId']] : NULL;
                $model->save();
                if (!empty($product['img'])) {
                    $image = DropImage::find()
                                      ->andWhere(['drop_id' => $model->id])
                                      ->one();
                    if (empty($image)) {
                        $this->_loadImageDrop($product['img'], $model->id);
                    }
                }
            }
            if ($product['type'] === 'set') {
                /** @var Drop $model */
                $model = Drop::find()
                             ->andWhere(['name' => $product['name']])
                             ->one();

                if (empty($model)) {
                    $model = new Drop();
                    $model->name = $product['name'];
                }
                $model->price = $product['price'];
                if ($product['itemEnabled']) {
                    $model->market_status = Drop::MARKET_STATUS_ACTIVE;
                } else {
                    $model->market_status = Drop::MARKET_STATUS_NOT_ACTIVE;
                }
                $model->status = Drop::STATUS_ACTIVE;
                $model->count = $product['amount'];
                if (!empty($product['about'])) {
                    $model->description = $product['about'];
                }
                if (!empty($product['data']) && !empty($product['data']['commands'])) {
                    $model->command = $product['data']['commands'];
                }
                $model->discount = $product['discount'];
                $model->category_id = !empty($cats[$product['categoryId']]) ? $cats[$product['categoryId']] : NULL;
                $model->type_id = !empty($types[$product['categoryId']]) ? $types[$product['categoryId']] : NULL;
                $model->save();
                if (!empty($product['subItems'])) {
                    foreach ($product['subItems'] as $subItem) {
                        /** @var Drop $model */
                        $subModel = Drop::find()
                                     ->andWhere(['rust_id' => $subItem['data']['itemId']])
                                     ->one();

                        if (!empty($subModel)) {
                            DropDrop::createRecord($model->id, $subModel->id, $subItem['amount']);
                        }
                    }
                }
                if (!empty($product['img'])) {
                    $image = DropImage::find()
                                      ->andWhere(['drop_id' => $model->id])
                                      ->one();
                    if (empty($image)) {
                        $this->_loadImageDrop($product['img'], $model->id);
                    }
                }
            }
        }
    }

    /**
     * Парсит пользователей с gamestores
     * gamestores/users-parsing
     *
     * @throws \Exception
     */
    public function actionUsersParsing()
    {
        $users = json_decode(file_get_contents(__DIR__ . "/../../players.json"), 1)['data'];

        foreach ($users as $user) {
            $model = User::find()
                ->andWhere(['steam_id' => $user['steamId']])
                ->one();
            if (empty($model)) {
                usleep(300);
                $model           = new User();
                $model->email    = "{$user['steamId']}@steam.com";
                $model->username = $user['username'];
                $model->steam_id = $user['steamId'];
                $model->setPassword(\Yii::$app->security->generateRandomString());
                $model->status     = User::STATUS_ACTIVE;
                $model->created_at = $user['registrationDate'];
                $model->generateAuthKey();
                $model->generateRefCode();
                $model->generateSocketRoom();
                if ($model->save()) {
                    $model->user_id = $model->id;
                    $model->update(false, ['user_id']);
                    $transaction = $model->getDb()->beginTransaction();
                    UserProfile::createModel($model, $user['username']);
                    // Сохраняем URL аватара из Steam вместо загрузки на сервер
                    try {
                        $imageLink = \common\components\oauth\Steam::getAvatar($user['steamId']);
                        if (!empty($imageLink)) {
                            $model->userProfile->steam_avatar_url = $imageLink;
                        }
                    } catch (\Exception $ex) {
                        //
                    }
                    $model->userProfile->save();
                    $auth = new Auth(
                        [
                            'user_id'   => $model->id,
                            'source'    => 'steam',
                            'source_id' => $user['steamId'],
                        ]
                    );
                    if ($auth->save()) {
                        $transaction->commit();
                    } else {
                        print_r($auth->getErrors());
                    }
                } else {
                    print_r($model->getErrors());
                }
            }

            if ($user['balance'] > 0) {
//                $userBalance = $model->getPersonalBalance();
//                $exists = Profit::find()
//                    ->andWhere(['user_balance_id' => $userBalance->id])
//                    ->andWhere(['type' => Profit::TYPE_TRANSFER_BALANCE])
//                    ->exists();
//                if (!$exists) {
//                    $profit                  = new Profit();
//                    $profit->status          = 1;
//                    $profit->type            = Profit::TYPE_TRANSFER_BALANCE;
//                    $profit->amount          = $user['balance'];
//                    $profit->user_balance_id = $userBalance->id;
//                    $profit->comment         = 'Перенос баланса с старого сайта';
//                    $profit->created_at      = date('Y-m-d H:i:s');
//                    $profit->save(false);
//                    $userBalance->recalculateBalance();
//                }
            }
        }
    }

    /**
     * Парсит Корзину с gamestores
     * gamestores/baskets-parsing
     *
     * @throws \Exception
     */
    public function actionBasketsParsing()
    {
        $baskets = json_decode(file_get_contents(__DIR__ . "/../../baskets.json"), 1)['data'];

        foreach ($baskets as $basket) {
            /** @var User $user */
            $user = User::find()
                         ->andWhere(['steam_id' => $basket['steam_id']])
                         ->one();
            if (empty($user)) continue;
            /** @var Drop $drop */
            $drop = Drop::find()
                ->andWhere(['rust_id' => $basket['item_id']])
                ->one();
            if (empty($drop)) {
                echo $basket['name'] . " " . $basket['item_id'] . PHP_EOL;
                continue;
                //break;
            }
            $exist = UserDrop::find()
                ->andWhere(['user_id' => $user->id])
                ->andWhere(['drop_id' => $drop->id])
                ->andWhere(['count' => $basket['amount']])
                ->andWhere(['created_at' => $basket['date_created']])
                ->exists();
            if (!empty($exist)) continue;

            $boxId = null;
            $setId = null;
            if ($basket['set']) {
                /** @var SetsDrop $sets */
                $sets = SetsDrop::find()
                    ->andWhere(['drop_id' => $drop->id])
                    ->andWhere(['count' => $basket['amount']])
                    ->one();
                if (!empty($sets)) {
                    $setId = $sets->sets_id;
                } else {
                    /** @var Box $box */
                    $box = Box::find()->one();
                    $boxId = $box->id;
                }
            }
            UserDrop::createRecord($user->id, $drop->id, $boxId, $setId, UserDrop::STATUS_ACTIVE, false, $basket['amount'], $basket['date_created']);
        }
    }

    private function _loadImageDrop($imageUrl, $dropId) {
        try {
            $image = DropImage::find()
                              ->andWhere(['drop_id' => $dropId])
                              ->one();
            if (!empty($image)) {
                $image->delete();
            }
            $uploadDir = \Yii::getAlias('@frontend/web/uploads');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir);
                chmod($uploadDir, 0777);
            }
            $fileUrl = "/drop/" . $dropId . "_" . md5(time()) . ".png";
            $filePath = $uploadDir . $fileUrl;
            file_put_contents($filePath, file_get_contents($imageUrl));
            DropImage::createRecord($fileUrl, DropImage::TYPE_ORIG, $dropId);
        } catch (\Exception $ex) {
            echo $imageUrl . PHP_EOL;
            echo $ex->getMessage() . PHP_EOL;
            echo "DropId: " . $dropId . PHP_EOL;
            echo PHP_EOL;
        }
    }

    private function _loadImage($imageUrl, $id) {
        $uploadDir = \Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }
}
