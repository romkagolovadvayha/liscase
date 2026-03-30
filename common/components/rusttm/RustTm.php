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
    public function history($date = null): array
    {
        $secretKey = Yii::$app->settings->get('rusttm_secretKey');
        $url = $this->baseUrl . "/history?key={$secretKey}";
        if (!empty($date)) {
            $url .= '&date=' . $date;
        }
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
        $attempts = [0, 2, 3];
        $response = null;
        foreach ($attempts as $sleep) {
            if ($sleep > 0) {
                sleep($sleep);
            }
            $response = Yii::$app->curl->get($url);
            if (!empty($response)) {
                break;
            }
            Yii::error(sprintf('RustTm buy empty response (sleep %d): %s', $sleep, $response), __METHOD__);
        }

        if (empty($response)) {
            Yii::error('RustTm buy failed: empty response after retries', __METHOD__);
            return [
                'success' => false,
                'error' => 'empty_response',
                'message' => 'Rust.tm returned empty response',
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            Yii::error('RustTm buy invalid JSON: ' . $response, __METHOD__);
            return [
                'success' => false,
                'error' => 'invalid_json',
                'message' => 'Rust.tm returned invalid response',
                'raw' => $response,
            ];
        }

        return $decoded;
    }

    /**
     * {@inheritdoc}
     */
    public function prices(): array
    {
        $uploadDir = Yii::getAlias('@frontend/web/uploads/prices');
        $file = $uploadDir . '/rusttm.json';

        if (!is_file($file)) {
            Yii::error('RustTm prices file not found: ' . $file, __METHOD__);
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            Yii::error('RustTm prices failed to read file: ' . $file, __METHOD__);
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = trim($content);
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            Yii::error(
                'RustTm prices invalid JSON: ' . json_last_error_msg() . ' (len=' . strlen($content) . ')',
                __METHOD__
            );
            return [];
        }

        return $decoded;
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
     * Справочник переводов типов предметов Rust
     * @return array
     */
    public static function getItemTypeTranslations(): array
    {
        return [
            'Armor' => 'Броня',
            'Hat' => 'Шляпа',
            'Mask' => 'Маска',
            'Backpack' => 'Рюкзак',
            'Tool' => 'Инструмент',
            'Resource' => 'Ресурс',
            'Food' => 'Еда',
            'Medical' => 'Медицина',
            'Construction' => 'Конструкция',
            'Electrical' => 'Электрика',
            'Fun' => 'Развлечение',
            'Misc' => 'Прочее',
            'Component' => 'Компонент',
            'Ammunition' => 'Боеприпасы',
            'Attire' => 'Одежда',
            'Common' => 'Обычный',
            'Uncommon' => 'Необычный',
            'Rare' => 'Редкий',
            'Very Rare' => 'Очень редкий',
            'Legendary' => 'Легендарный',
            'Weapon' => 'Оружие',
            'Clothing' => 'Одежда',
            'Tool' => 'Инструмент',
            'Resource' => 'Ресурс',
            'Food' => 'Еда',
            'Medical' => 'Медицина',
            'Construction' => 'Конструкция',
            'Electrical' => 'Электрика',
            'Fun' => 'Развлечение',
            'Misc' => 'Прочее',
            'Component' => 'Компонент',
            'Ammunition' => 'Боеприпасы',
            'Attire' => 'Одежда',
            'Common' => 'Обычный',
            'Uncommon' => 'Необычный',
            'Rare' => 'Редкий',
            'Very Rare' => 'Очень редкий',
            'Legendary' => 'Легендарный',
        ];
    }

    /**
     * Получить перевод типа предмета
     * @param string $type
     * @return string
     */
    public static function translateItemType(string $type): string
    {
        $translations = self::getItemTypeTranslations();
        return $translations[$type] ?? $type;
    }

    /**
     * {@inheritdoc}
     */
    public function items(): array
    {
        $cacheKey = "RustTm4_items";
        $cacheKeyCategories = "RustTm5_categories";
        $cachedItems = Yii::$app->cache->get($cacheKey);
        if ($cachedItems) {
            return $cachedItems;
        }
        $result = [];
        $prices = $this->prices();
        if (empty($prices['items']) || !is_array($prices['items'])) {
            Yii::error('RustTm items(): prices item list is empty', __METHOD__);
            return [];
        }
        $items = $prices['items'];
        $itemsName = [];
        $categories = [];
        foreach ($items as $id => $item) {
            if (!is_array($item)) {
                continue;
            }
            $priceRaw = (float)($item['price'] ?? 0);
            if ($priceRaw > 5000) {
                continue;
            }
            if ($priceRaw < 5) {
                continue;
            }
            $avgPrice = isset($item['avg_price']) && $item['avg_price'] !== null && $item['avg_price'] !== ''
                ? (float)$item['avg_price']
                : $priceRaw;
            $marketName = (string)($item['market_hash_name'] ?? '');
            $ruName = (string)($item['ru_name'] ?? '');
            if (strpos($marketName, 'Key') !== false) {
                continue;
            }
            if (strpos($marketName, 'Case') !== false) {
                continue;
            }
            if (strpos($ruName, 'Наклейка') !== false) {
                continue;
            }
            if (strpos($marketName, '2017') !== false
                || strpos($marketName, '2018') !== false
                || strpos($marketName, '2019') !== false
                || strpos($marketName, '2020') !== false
                || strpos($marketName, '2021') !== false
                || strpos($marketName, '2022') !== false
                || strpos($marketName, '2023') !== false
                || strpos($marketName, '2024') !== false
                || strpos($marketName, 'Operation') !== false
                || strpos($marketName, 'Music') !== false
                || strpos($marketName, 'Patch ') !== false
                || strpos($marketName, 'Graffiti') !== false
                || strpos($marketName, 'Capsule') !== false
                || strpos($marketName, ' Pin') !== false
                || strpos($marketName, 'Sticker') !== false) {
                continue;
            }
            $diff = $priceRaw > 0 ? round(($avgPrice - $priceRaw) / $priceRaw * 100, 2) : 0;
            if ($diff < -10) {
                continue;
            }
//            if (in_array($item['market_hash_name'], ['Weapon Barrel','Neanderthal Chestplate','Tooth Monster Pants','Pumpkin Hoodie','Cargo Heli Hatchet','Twisted Metal Furnace','Cardboard Sheet Metal Door','Cargo Heli Hatchet','Gore AR','Gingerbread Python','Gift Stack Backpack','Slime Monster Helmet','Adobe Furnace','Cheese Poncho','Air Conditioner Box','White Holographic Pants','Heater AR','Air Conditioner Box','Nightmare Clown Burlap Pants','Tooth Monster Hoodie','Oasis Door','Zombie Facemask','Beyond Reason Wood Door','Nightmare Clown Balaclava','Danger Barricade','High Quality Bag', 'High Quality Crate', 'Low Quality Bag', 'Black Diamond Gloves', 'Ultramarine Small Box', 'Ultramarine Large Box', 'Pumpkin Pants', 'Wrapped Facemask', 'Nightmare Clown Burlap Shirt', 'Mummy Wrap Jacket'])) {
//                continue;
//            }
            $ceilPrice = ceil($priceRaw);
            if (in_array($marketName . "_" . $ceilPrice, $itemsName)) {
                continue;
            }
            $title = explode(' | ', $marketName);
            $title = $title[count($title) - 1];
            $name = urlencode($marketName);
            $name = str_replace('+', '%20', $name);

            $category = $marketName;
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

            $titleRu = explode(' | ', $ruName);
            $titleRu = $titleRu[count($titleRu) - 1];
            $ruQuality = (string)($item['ru_quality'] ?? '');
            $titleRu = str_replace(' (' . $ruQuality . ')', '', $titleRu);
            $itemsName[] = $marketName . "_" . $ceilPrice;
            $result[$id] = [
                "id" => $id,
                "diff" => $diff,
                "name_search" => $marketName . $ruName,
                "name" => $title,
                "ru_name" => $titleRu,
                "market_hash_name" => $marketName,
                "category" => $category,
                // Округляем цену в большую сторону (умножаем на 1.3 и применяем ceil)
                "price" => ceil($priceRaw * 1.3),
                "popularity_7d" => $item['popularity_7d'] ?? null,
                "ru_quality" => $ruQuality,
                "text_color" => $item['text_color'] ?? '',
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
