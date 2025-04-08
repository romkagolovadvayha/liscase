<?php

namespace common\components\rusttm;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class RustTm
{

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://rust.tm/api/v2';
    //public $baseUrl = 'https://market.csgo.com/api/v2';

    /**
     * {@inheritdoc}
     */
    public function history(): array
    {
        $secretKey = Yii::$app->settings->get('rusttm_secretKey');
        $url = $this->baseUrl . "/history?key={$secretKey}";
        $response = Yii::$app->curl->get($url);
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function buy($name, $price, $partner, $token): array
    {
        $secretKey = Yii::$app->settings->get('rusttm_secretKey');
        $url = $this->baseUrl . "/buy-for?key={$secretKey}&hash_name=".urlencode($name)."&price={$price}&partner={$partner}&token={$token}";
        $response = Yii::$app->curl->get($url);
        if (empty($response)) {
            sleep(2);
            $response = Yii::$app->curl->get($url);
            Yii::error('RustTm buy 2: ' .  $response);
        }
        if (empty($response)) {
            sleep(3);
            $response = Yii::$app->curl->get($url);
            Yii::error('RustTm buy 3: ' .  $response);
        }
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function prices(): array
    {
        $url = $this->baseUrl . "/prices/class_instance/RUB.json";
        $response = Yii::$app->curl->get($url);
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function items(): array
    {
        $cacheKey = "RustTm_items";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $result = [];
        $items = $this->prices()['items'];
        foreach ($items as $id => $item) {
            if ($item['price'] > 5000) {
                continue;
            }
            if ($item['price'] < 10) {
                continue;
            }
            if (empty($item['avg_price'])) {
                continue;
            }
            $diff = $item['price'] - $item['avg_price'];
            if ($diff > 20) {
                continue;
            }
//            if (in_array($item['market_hash_name'], ['Weapon Barrel','Neanderthal Chestplate','Tooth Monster Pants','Pumpkin Hoodie','Cargo Heli Hatchet','Twisted Metal Furnace','Cardboard Sheet Metal Door','Cargo Heli Hatchet','Gore AR','Gingerbread Python','Gift Stack Backpack','Slime Monster Helmet','Adobe Furnace','Cheese Poncho','Air Conditioner Box','White Holographic Pants','Heater AR','Air Conditioner Box','Nightmare Clown Burlap Pants','Tooth Monster Hoodie','Oasis Door','Zombie Facemask','Beyond Reason Wood Door','Nightmare Clown Balaclava','Danger Barricade','High Quality Bag', 'High Quality Crate', 'Low Quality Bag', 'Black Diamond Gloves', 'Ultramarine Small Box', 'Ultramarine Large Box', 'Pumpkin Pants', 'Wrapped Facemask', 'Nightmare Clown Burlap Shirt', 'Mummy Wrap Jacket'])) {
//                continue;
//            }
            $result[$id] = [
                "id" => $id,
                "diff" => $diff,
                "name" => $item['market_hash_name'],
                "price" => $item['price'] * 1.3,
                "popularity_7d" => $item['popularity_7d'],
                "image" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/100.png",
                "image300" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/300.png"
            ];
        }
        Yii::$app->cache->set($cacheKey, $result, 60);
        return $result;
    }
}
