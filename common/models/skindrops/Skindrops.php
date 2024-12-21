<?php

namespace common\models\skindrops;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $name
 * @property string              $steam_id
 * @property string              $player
 * @property string              $price
 * @property string              $real_price
 * @property string              $image
 * @property string              $replay
 * @property string              $created_at
 */
class Skindrops extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'skindrops';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'steam_id'   => Yii::t('common', 'Steam ID'),
            'player'     => Yii::t('common', 'Имя игрока'),
            'name'       => Yii::t('common', 'Скин'),
            'price'      => Yii::t('common', 'Цена в стиме'),
            'real_price' => Yii::t('common', 'Цена в маркете'),
            'image'      => Yii::t('common', 'Изображение'),
            'replay'     => Yii::t('common', 'Ссылка повторить покупку'),
            'created_at' => Yii::t('common', 'Тип'),
        ];
    }

    public static function getUrlQuery($url, $key = null) {
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (is_null($key)) {
                return $query;
            } elseif (isset($query[$key])) {
                return $query[$key];
            }
        }

        return false;
    }

    public static function sendSkin($user) {
        $partner = Skindrops::getUrlQuery($user->userProfile->trade_link, 'partner');
        $token = Skindrops::getUrlQuery($user->userProfile->trade_link, 'token');

        if (empty($partner) || empty($token)) {
            $user->userProfile->skindrops = 0;
            $user->userProfile->skindrops_error = 'Неверная ссылка для обмена';
            $user->userProfile->save(false);
            return false;
        }

        $minPrice = 20;
        $maxPrice = 50;
        $items = [];
        $data = Yii::$app->rustTm->prices()['items'];
        shuffle($data);
        foreach ($data as $item) {
            if ($item['price'] > $item['avg_price']) {
                continue;
            }
            if ($item['price'] > $maxPrice || $item['price'] < $minPrice) {
                continue;
            }
            $items[] = [
                "name" => $item['market_hash_name'],
                "price" => $item['price'] + 10,
                "image" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/100.png",
                "image300" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/300.png"
            ];
            if (count($items) > 40) {
                break;
            }
        }
        $item = $items[0];

        $response = Yii::$app->rustTm->buy($item['name'], $item['price'] * 100, $partner, $token);

        return [
            'response' => $response,
            'item' => $item,
        ];
    }

}
