<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\models\box\Box;
use common\models\box\Category;
use common\models\box\Drop;
use common\models\box\DropImage;
use common\models\box\DropType;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use yii\console\Controller;

class DropParserController extends Controller
{
    /**
     * Парсит предметы с ru.rustlabs.com
     * drop-parser/get-drops
     *
     * @throws \Exception
     */
    public function actionGetDrops()
    {
        $itemsList = $this->getItemsList();
    }

    private function getItemsList() {
        $response = '<h2>Развлечение</h2>
    <a href="/item/acoustic-guitar" class="pad"><span class="l-cell"><img
            src="//rustlabs.com/img/items40/fun.guitar.png" alt="Акустическая гитара"></span><span class="r-cell">Акустическая гитара</span></a><a
        href="/item/junkyard-drum-kit" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/drumkit.png" alt="Барабанная установка из хлама"></span><span class="r-cell">Барабанная установка из хлама</span></a><a
        href="/item/shovel-bass" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/fun.bass.png"
                                                                       alt="Бас-лопата"></span><span class="r-cell">Бас-лопата</span></a><a
        href="/item/canbourine" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.tambourine.png" alt="Бубен"></span><span class="r-cell">Бубен</span></a><a
        href="/item/jerry-can-guitar" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.jerrycanguitar.png" alt="Гитара из канистры"></span><span class="r-cell">Гитара из канистры</span></a><a
        href="/item/disco-floor" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/discofloor.png"
                                                                       alt="Диско-танцпол"></span><span class="r-cell">Диско-танцпол</span></a><a
        href="/item/disco-ball" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/discoball.png"
                                                                      alt="Диско-шар"></span><span class="r-cell">Диско-шар</span></a><a
        href="/item/boogie-board" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/boogieboard.png"
                                                                        alt="Доска"></span><span
        class="r-cell">Доска</span></a><a href="/item/wrapped-gift" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/wrappedgift.png" alt="Завернутый подарок"></span><span class="r-cell">Завернутый подарок</span></a><a
        href="/item/green-boomer" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.boomer.green.png" alt="Зеленый фейерверк"></span><span class="r-cell">Зеленый фейерверк</span></a><a
        href="/item/above-ground-pool" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/abovegroundpool.png" alt="Каркасный бассейн"></span><span class="r-cell">Каркасный бассейн</span></a><a
        href="/item/cassette-long" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/cassette.png"
                                                                         alt="Кассета - Длинная"></span><span
        class="r-cell">Кассета - Длинная</span></a><a href="/item/cassette-short" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/cassette.short.png" alt="Кассета - Короткая"></span><span class="r-cell">Кассета - Короткая</span></a><a
        href="/item/cassette-medium" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/cassette.medium.png" alt="Кассета - Средняя"></span><span class="r-cell">Кассета - Средняя</span></a><a
        href="/item/cassette-recorder" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.casetterecorder.png" alt="Кассетный диктофон"></span><span class="r-cell">Кассетный диктофон</span></a><a
        href="/item/portable-boom-box" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.boomboxportable.png" alt="Кассетный магнитофон"></span><span class="r-cell">Кассетный магнитофон</span></a><a
        href="/item/cowbell" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/fun.cowbell.png"
                                                                   alt="Колокольчик"></span><span class="r-cell">Колокольчик</span></a><a
        href="/item/red-boomer" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.boomer.red.png" alt="Красный фейерверк"></span><span class="r-cell">Красный фейерверк</span></a><a
        href="/item/xylobone" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/xylophone.png"
                                                                    alt="Ксилофон"></span><span
        class="r-cell">Ксилофон</span></a><a href="/item/laser-light" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/laserlight.png" alt="Лазерный проектор"></span><span class="r-cell">Лазерный проектор</span></a><a
        href="/item/beach-chair" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/beachchair.png"
                                                                       alt="Лежак"></span><span
        class="r-cell">Лежак</span></a><a href="/item/firecracker-string" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/lunar.firecrackers.png" alt="Лента фейерверков"></span><span class="r-cell">Лента фейерверков</span></a><a
        href="/item/megaphone" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/megaphone.png"
                                                                     alt="Мегафон"></span><span
        class="r-cell">Мегафон</span></a><a href="/item/microphone-stand" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/microphonestand.png" alt="Микрофонная стойка"></span><span class="r-cell">Микрофонная стойка</span></a><a
        href="/item/mobile-phone" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/mobilephone.png"
                                                                        alt="Мобильный телефон"></span><span
        class="r-cell">Мобильный телефон</span></a><a href="/item/sound-light" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/soundlight.png" alt="Музыкальная лампа"></span><span class="r-cell">Музыкальная лампа</span></a><a
        href="/item/boom-box" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/boombox.png"
                                                                    alt="Музыкальный центр"></span><span class="r-cell">Музыкальный центр</span></a><a
        href="/item/paddling-pool" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/paddlingpool.png" alt="Надувной бассейн"></span><span class="r-cell">Надувной бассейн</span></a><a
        href="/item/sky-lantern" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/skylantern.png"
                                                                       alt="Небесный фонарик"></span><span
        class="r-cell">Небесный фонарик</span></a><a href="/item/new-year-gong" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/newyeargong.png" alt="Новогодний гонг"></span><span class="r-cell">Новогодний гонг</span></a><a
        href="/item/wrapping-paper" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/wrappingpaper.png" alt="Оберточная бумага"></span><span class="r-cell">Оберточная бумага</span></a><a
        href="/item/orange-boomer" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.boomer.orange.png" alt="Оранжевый фейерверк"></span><span
        class="r-cell">Оранжевый фейерверк</span></a><a href="/item/pan-flute" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.flute.png" alt="Пан-флейта"></span><span
        class="r-cell">Пан-флейта</span></a><a href="/item/pinata" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/pinata.png" alt="Пиньята"></span><span class="r-cell">Пиньята</span></a><a
        href="/item/beach-towel" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/beachtowel.png"
                                                                       alt="Пляжное полотенце"></span><span
        class="r-cell">Пляжное полотенце</span></a><a href="/item/beach-parasol" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/beachparasol.png" alt="Пляжный зонтик"></span><span class="r-cell">Пляжный зонтик</span></a><a
        href="/item/beach-table" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/beachtable.png"
                                                                       alt="Пляжный столик"></span><span class="r-cell">Пляжный столик</span></a><a
        href="/item/green-roman-candle" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.romancandle.green.png" alt="Римская свеча (зеленая)"></span><span
        class="r-cell">Римская свеча (зеленая)</span></a><a href="/item/red-roman-candle" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.romancandle.red.png" alt="Римская свеча (красная)"></span><span
        class="r-cell">Римская свеча (красная)</span></a><a href="/item/blue-roman-candle" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.romancandle.blue.png" alt="Римская свеча (синяя)"></span><span
        class="r-cell">Римская свеча (синяя)</span></a><a href="/item/violet-roman-candle" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.romancandle.violet.png"
                            alt="Римская свеча (фиолетовая)"></span><span
        class="r-cell">Римская свеча (фиолетовая)</span></a><a href="/item/wheelbarrow-piano" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/piano.png" alt="Рояль на тачке"></span><span class="r-cell">Рояль на тачке</span></a><a
        href="/item/sled" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/sled.png"
                                                                alt="Санки"></span><span class="r-cell">Санки</span></a><a
        href="/item/blue-boomer" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.boomer.blue.png" alt="Синий фейерверк"></span><span class="r-cell">Синий фейерверк</span></a><a
        href="/item/inner-tube" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/innertube.png"
                                                                      alt="Спасательный круг"></span><span
        class="r-cell">Спасательный круг</span></a><a href="/item/sousaphone" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.tuba.png" alt="Сузафон"></span><span class="r-cell">Сузафон</span></a><a
        href="/item/telephone" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/telephone.png"
                                                                     alt="Телефон"></span><span
        class="r-cell">Телефон</span></a><a href="/item/skull-trophy" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/skull.trophy.png" alt="Трофей с черепом"></span><span class="r-cell">Трофей с черепом</span></a><a
        href="/item/plumber\'s-trumpet" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/fun.trumpet.png" alt="Труба сантехника"></span><span class="r-cell">Труба сантехника</span></a><a
        href="/item/connected-speaker" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/connected.speaker.png" alt="Уличный громкоговоритель"></span><span
        class="r-cell">Уличный громкоговоритель</span></a><a href="/item/pattern-boomer" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.boomer.pattern.png"
                            alt="Фейерверк (настраиваемый)"></span><span class="r-cell">Фейерверк (настраиваемый)</span></a><a
        href="/item/white-volcano-firework" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.volcano.png" alt="Фейерверк «Белый вулкан»"></span><span
        class="r-cell">Фейерверк «Белый вулкан»</span></a><a href="/item/red-volcano-firework" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.volcano.red.png" alt="Фейерверк «Красный вулкан»"></span><span
        class="r-cell">Фейерверк «Красный вулкан»</span></a><a href="/item/violet-volcano-firework" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.volcano.violet.png"
                            alt="Фейерверк «Фиолетовый вулкан»"></span><span class="r-cell">Фейерверк «Фиолетовый вулкан»</span></a><a
        href="/item/champagne-boomer" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/firework.boomer.champagne.png" alt="Фейерверк Брызги шампанского"></span><span
        class="r-cell">Фейерверк Брызги шампанского</span></a><a href="/item/violet-boomer" class="pad"><span
        class="l-cell"><img src="//rustlabs.com/img/items40/firework.boomer.violet.png"
                            alt="Фиолетовый фейерверк"></span><span class="r-cell">Фиолетовый фейерверк</span></a><a
        href="/item/confetti-cannon" class="pad"><span class="l-cell"><img
        src="//rustlabs.com/img/items40/confetticannon.png" alt="Хлопушка с конфетти"></span><span class="r-cell">Хлопушка с конфетти</span></a><a
        href="/item/skull-spikes" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/skullspikes.png"
                                                                        alt="Черепа на копьях"></span><span
        class="r-cell">Черепа на копьях</span></a>';
        //$response = file_get_contents('https://ru.rustlabs.com/group=itemlist');
        preg_match('|<h2>(.*)</h2>|is', $response, $m );
        $catName = $m[1];

        $google = new TranslateApi();
        $catEn = strtolower($google->translateText($catName));
        //preg_match_all('/<a(.*?)</a>/', $response, $m);
        //preg_match_all('/src=\"(.*?)\"/', $response, $m);
        preg_match_all('~<a.*?>(.*?)</a>~is', $response, $m );
        foreach ($m[0] as $link) {
            sleep(1);
            preg_match('/src=\"(.*?)\"/', $link, $im);
            $imageOriginalLink = 'https:' . str_replace('items40', 'items180', $im[1]);
            preg_match('/alt=\"(.*?)\"/', $link, $na);
            $name = $na[1];
            preg_match('/href=\"(.*?)\"/', $link, $na);
            $link  = 'https://ru.rustlabs.com' . $na[1];
            $itemResponse = file_get_contents($link);
            preg_match('~<p class=\"description\">(.*?)</p>~is', $itemResponse, $de);
            $description  = $de[1];
            preg_match('~<table class=\"stats-table\">(.*?)</table>~is', $itemResponse, $tab);
            $table  = $tab[1];
            preg_match_all('~<td>(.*?)</td>~is', $table, $tds);
            $id = $tds[1][1];

            $nameHash = explode('/', $link);
            $nameHash = $nameHash[array_key_last($nameHash)];

            $drop = new Drop();
            $drop->status = 1;
            $drop->created_at = date('Y-m-d H:i:s');
            $exist = Drop::find()
                         ->andWhere(['name' => $drop->name])
                         ->exists();
            if ($exist) {
                continue;
            }
            $drop->name = $name;
            $drop->eng_name = $nameHash;
            $drop->price = 0;
            $drop->quality = null;
            $drop->rust_id = $id;
            $drop->description = $description;
            $drop->type_id = DropType::createRecord($catName, $catEn);
            $drop->save();

            $dropId = \Yii::$app->db->getLastInsertID();
            $this->_loadImage($imageOriginalLink, $dropId);
            echo $name . PHP_EOL;
        }
        //<a href="/item/skull-spikes" class="pad"><span class="l-cell"><img src="//rustlabs.com/img/items40/skullspikes.png" alt="Черепа на копьях"></span><span class="r-cell">Черепа на копьях</span></a>
        //print_r($response);
        exit;
    }

    private function _loadImage($imageUrl, $dropId) {
        $uploadDir = \Yii::getAlias('@frontend/web/uploads');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir);
            chmod($uploadDir, 0777);
        }
        $fileUrl = "/drop/" . $dropId . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . $fileUrl;
        file_put_contents($filePath, file_get_contents($imageUrl));
        DropImage::createRecord($fileUrl, DropImage::TYPE_ORIG, $dropId);
    }

    /**
     * drop-parser/new-items
     * @throws \Exception
     */
    public function actionNewItems() {
        $curl = clone \Yii::$app->curl;
        $items = json_decode($curl->get('https://prostoj.store/api/items'), 1);

        $drops = \common\models\box\Drop::find()
            ->indexBy('eng_name')
            ->all();

        $isInsert = false;
        $google = new TranslateApi();
        foreach ($items as $item) {
            if (empty($drops[$item['eng_name']])) {
                $model = new Drop();
                $model->name = $item['name'];
                $model->description = $item['description'];
                $model->eng_name = $item['eng_name'];
                $model->rust_id = $item['rust_id'];
                $model->type_id = $item['type_id'];
                if (!empty($item['category_name'])) {
                    $categoryBD = Category::find()
                                          ->andWhere(['name' => $item['category_name']])
                                          ->one();
                    if (!empty($categoryBD)) {
                        $model->category_id = $categoryBD->id;
                    } else {
                        $categoryTag = strtolower($google->translateText($item['category_name']));
                        $lastId = Category::createRecord($item['category_name'], $categoryTag);
                        $model->category_id = $lastId;
                    }
                }
                $model->blocked_hour = $item['blocked_hour'];
                $model->save();
                $this->_loadImage($item['image'], $model->id);
                $isInsert = true;
            } else {
                $drops[$item['eng_name']]->name = $item['name'];
                $drops[$item['eng_name']]->save();
            }
        }

        if ($isInsert) {
            Drop::updateCache();
            \Yii::$app->runAction('translate/import-api');
        }
    }
}
