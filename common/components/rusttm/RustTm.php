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
        $name = rawurlencode($name);
        $url = $this->baseUrl . "/buy-for?key={$secretKey}&hash_name=".$name."&price={$price}&partner={$partner}&token={$token}";
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
        $uploadDir = Yii::getAlias('@frontend/web/uploads/prices');
        return json_decode(file_get_contents($uploadDir . '/rusttm.json'), true);
    }

    public function categories(): array
    {
        $this->items();
        $cacheKeyCategories = "RustTm5_categories";
        if (Yii::$app->cache->get($cacheKeyCategories)) {
            return Yii::$app->cache->get($cacheKeyCategories);
        }
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function items(): array
    {
        $cacheKey = "RustTm4_items";
        $cacheKeyCategories = "RustTm5_categories";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $result = [];
        $items = $this->prices()['items'];
        $itemsName = [];
        $categories = [];
        foreach ($items as $id => $item) {
            if ($item['price'] > 5000) {
                continue;
            }
            if ($item['price'] < 5) {
                continue;
            }
            if (empty($item['avg_price'])) {
                continue;
            }
            if (strpos($item['market_hash_name'], 'Key') !== false) {
                continue;
            }
            if (strpos($item['market_hash_name'], 'Case') !== false) {
                continue;
            }
            if (strpos($item['ru_name'], 'Наклейка') !== false) {
                continue;
            }
            if (strpos($item['market_hash_name'], '2017') !== false
                || strpos($item['market_hash_name'], '2018') !== false
                || strpos($item['market_hash_name'], '2019') !== false
                || strpos($item['market_hash_name'], '2020') !== false
                || strpos($item['market_hash_name'], '2021') !== false
                || strpos($item['market_hash_name'], '2022') !== false
                || strpos($item['market_hash_name'], '2023') !== false
                || strpos($item['market_hash_name'], '2024') !== false
                || strpos($item['market_hash_name'], 'Operation') !== false
                || strpos($item['market_hash_name'], 'Music') !== false
                || strpos($item['market_hash_name'], 'Patch ') !== false
                || strpos($item['market_hash_name'], 'Graffiti') !== false
                || strpos($item['market_hash_name'], 'Capsule') !== false
                || strpos($item['market_hash_name'], ' Pin') !== false
                || strpos($item['market_hash_name'], 'Sticker') !== false) {
                continue;
            }
            $diff = round(($item['avg_price'] - $item['price']) / $item['price'] * 100, 2);
            if ($diff < -10) {
                continue;
            }
//            if (in_array($item['market_hash_name'], ['Weapon Barrel','Neanderthal Chestplate','Tooth Monster Pants','Pumpkin Hoodie','Cargo Heli Hatchet','Twisted Metal Furnace','Cardboard Sheet Metal Door','Cargo Heli Hatchet','Gore AR','Gingerbread Python','Gift Stack Backpack','Slime Monster Helmet','Adobe Furnace','Cheese Poncho','Air Conditioner Box','White Holographic Pants','Heater AR','Air Conditioner Box','Nightmare Clown Burlap Pants','Tooth Monster Hoodie','Oasis Door','Zombie Facemask','Beyond Reason Wood Door','Nightmare Clown Balaclava','Danger Barricade','High Quality Bag', 'High Quality Crate', 'Low Quality Bag', 'Black Diamond Gloves', 'Ultramarine Small Box', 'Ultramarine Large Box', 'Pumpkin Pants', 'Wrapped Facemask', 'Nightmare Clown Burlap Shirt', 'Mummy Wrap Jacket'])) {
//                continue;
//            }
            $ceilPrice = ceil($item['price']);
            if (in_array($item['market_hash_name'] . "_" . $ceilPrice, $itemsName)) {
                continue;
            }
            $title = explode(' | ', $item['market_hash_name']);
            $title = $title[count($title) - 1];
            $name = urlencode($item['market_hash_name']);
            $name = str_replace('+', '%20', $name);

            $category = $item['market_hash_name'];
            $statTrak = false;
            if (strpos($category, 'StatTrak™') !== false) {
                $category = str_replace('StatTrak™ ', ' ', $category);
                $statTrak = true;
            }
            $category = explode('|', $category);
            $category = $category[0];
            $category = trim($category);
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }

            $titleRu = explode(' | ', $item['ru_name']);
            $titleRu = $titleRu[count($titleRu) - 1];
            $titleRu = str_replace(' (' . $item['ru_quality'] . ')', '', $titleRu);
            $itemsName[] = $item['market_hash_name'] . "_" . $ceilPrice;
            $result[$id] = [
                "id" => $id,
                "diff" => $diff,
                "name_search" => $item['market_hash_name'] . $item['ru_name'],
                "name" => $title,
                "ru_name" => $titleRu,
                "market_hash_name" => $item['market_hash_name'],
                "category" => $category,
                "price" => ceil($item['price'] * 1.3),
                "popularity_7d" => $item['popularity_7d'],
                "ru_quality" => $item['ru_quality'],
                "text_color" => $item['text_color'],
                "image" => "https://cdn.rust.tm/item/" . $name . "/100.png",
                "image300" => "https://cdn.rust.tm/item/" . $name . "/300.png",
                "statTrak" => $statTrak,
            ];
        }
        Yii::$app->cache->set($cacheKey, $result, 60);
        Yii::$app->cache->set($cacheKeyCategories, $categories, 60);
        return $result;
    }
}
