<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\components\helpers\CurrencyHelper;
use common\components\queue\process\BuyDropJob;
use common\models\invoice\Invoice;
use common\models\statistics\Statistics;
use common\models\user\UserDrop;
use common\models\user\UserVip;
use Yii;
use yii\base\BaseObject;
use yii\web\JsExpression;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $eng_name
 * @property string      $quality
 * @property string      $market_status
 * @property int         $min_box
 * @property int         $max_box
 * @property string      $description
 * @property string      $market_id
 * @property int         $count
 * @property int         $discount
 * @property int         $category_id
 * @property int         $drop_type
 * @property string      $rust_id
 * @property string      $command
 * @property string      $type_id
 * @property float       $price
 * @property int         $blocked_hour
 * @property string      $blocked_at
 * @property int         $status
 * @property string      $created_at
 * @property bool        $show_main_block
 * @property int         $sort
 * @property int         $floating_price_percent  Максимальный процент колебания цены
 * @property int         $full_only 1 - можно выводить только целиком, 0 - можно частично
 * @property int         $is_blocked_building
 *
 * @property DropImage[] $dropImages
 * @property DropDrop[] $subDrops
 * @property DropImage   $imageOrig
 * @property DropImage   $imageOrig2
 * @property DropType    $type
 * @property string      $priceCeil
 * @property string      $priceMarket
 * @property string      $currency
 * @property BoxDrop     $boxDrop
 * @property Category     $category
 * @property DropStat[]     $dropStat
 */
class Drop extends ActiveRecord
{
    private $_imageOrigUrl;
    private $_imageOrig2Url;

    /** Кэш одной строки drop (+ imageOrig) для API/заданий, сек. Инвалидация: {@see invalidateApiRowCache()}, afterSave/afterDelete. */
    public const API_ROW_CACHE_TTL = 300;

    const STATUS_NOT_ACTIVE   = 0;
    const STATUS_ACTIVE       = 1;
    const MARKET_STATUS_NOT_ACTIVE   = 0;
    const MARKET_STATUS_ACTIVE       = 1;

    const TYPE_DROP    = 0;
    const TYPE_COMMAND = 1;
    const TYPE_SET     = 2;
    const TYPE_SELECT  = 3;
    const TYPE_VIP     = 4;

    /** RCON по всем активным серверам для VIP из магазина, если в товаре пусто поле command */
    public const VIP_STORE_RCON_DEFAULT = 'addgroup %STEAMID% vip_status 30d';

    /**
     * @return array
     */
    public static function getDropTypesList(): array
    {
        return [
            self::TYPE_DROP       => Yii::t('common', 'Предмет'),
            self::TYPE_COMMAND       => Yii::t('common', 'Команда'),
            self::TYPE_SET       => Yii::t('common', 'Набор предметов'),
            self::TYPE_SELECT       => Yii::t('common', 'Товар с выбором'),
            self::TYPE_VIP       => Yii::t('common', 'Вип'),
        ];
    }
    /**
     * @return array
     */
    public static function getMarketStatusList(): array
    {
        return [
            self::MARKET_STATUS_NOT_ACTIVE       => Yii::t('common', 'Не активный'),
            self::MARKET_STATUS_ACTIVE       => Yii::t('common', 'Активный'),
        ];
    }
    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_NOT_ACTIVE       => Yii::t('common', 'Не активный'),
            self::STATUS_ACTIVE       => Yii::t('common', 'Активный'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'eng_name'               => Yii::t('common', 'short_key (в игре)'),
            'quality'               => Yii::t('common', 'Качество'),
            'description'               => Yii::t('common', 'Описание'),
            'category_id'               => Yii::t('common', 'Категория'),
            'market_id'               => Yii::t('common', 'ID в маргете'),
            'rust_id'               => Yii::t('common', 'Идентификатор в игре'),
            'market_status'               => Yii::t('common', 'Статус в магазине'),
            'count'               => Yii::t('common', 'Количество в маркете'),
            'discount'               => Yii::t('common', 'Скидка'),
            'min_box'               => Yii::t('common', 'Минимальное кол-во в рулетке'),
            'max_box'               => Yii::t('common', 'Максимальное кол-во в рулетке'),
            'type_id'               => Yii::t('common', 'Тип'),
            'status'               => Yii::t('common', 'Статус'),
            'price'              => Yii::t('common', 'Цена'),
            'created_at'          => Yii::t('common', 'Дата создания'),
            'command'          => Yii::t('common', 'Команда'),
            'blocked_hour'          => Yii::t('common', 'Вайп блок (часов)'),
            'show_main_block'              => Yii::t('common', 'Показывать в главном блоке главной страницы'),
            'sort'          => Yii::t('common', 'Сортировка'),
            'drop_type'          => Yii::t('common', 'Тип предмета'),
            'floating_price_percent' => Yii::t('common', 'Плавающая цена (%)'),
            'full_only' => Yii::t('common', 'Выводить только целиком'),
            'is_blocked_building' => Yii::t('common', 'Запретить выводить в зоне чужого шкафа'),
        ];
    }

    /**
     * @return mixed|string
     */
    public function getShortName() {
        $array = explode('|', Yii::t('database', $this->name));
        if (count($array) < 2) {
            return Yii::t('database', $this->name);
        }
        $array2 = explode('(', $array[1]);
        if (count($array2) < 2) {
            return trim($array[1]);
        }
        return trim($array2[0]);
    }

    /**
     * @return array
     */
    public static function getQualityList() {
        $all = Drop::find()
            ->cache(24 * 60 * 60)
            ->select('quality')
            ->andWhere(['status' => Drop::STATUS_ACTIVE])
            ->distinct(true)
            ->indexBy('quality')
            ->asArray()
            ->all();

        $result = [];
        foreach ($all as $index => $item) {
            $result[$index] = Yii::t('database', $index);
        }
        return $result;
    }

    /**
     * @return string
     */
    public static function getPriceMax() {
        $result = Drop::find()
                      ->cache(24 * 60 * 60)
                      ->select('MAX(price)')
                      ->andWhere(['status' => Drop::STATUS_ACTIVE])
                      ->createCommand()
                      ->queryScalar();
        return ceil($result);
    }

    /**
     * @return mixed|string
     */
    public function getLevel() {
        $level = 0;
        if ($this->priceCeil > 100) {
            $level = 1;
        }
        if ($this->priceCeil > 500) {
            $level = 2;
        }
        if ($this->priceCeil > 1000) {
            $level = 3;
        }
        return $level;
    }

    public function rules(): array
    {
        return [
            [['status', 'type_id', 'category_id', 'sort', 'show_main_block', 'drop_type'], 'integer'],
            [['name', 'market_id', 'eng_name', 'quality'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['created_at','price'], 'safe'],
            ['floating_price_percent', 'integer', 'min' => 0, 'max' => 100],
            [['full_only', 'is_blocked_building'], 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function saveRecord(): bool
    {
        try {
            $this->save(false);
        } catch (\Exception $e) {
            \Yii::info("box file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

    /**
     * Ключ кэша строки drop для API (по id).
     */
    public static function apiRowCacheKey(int $id): string
    {
        return 'drop_api_row_v1_' . $id;
    }

    /**
     * Сброс кэша строки drop (после правок в админке, смены картинки и т.п.).
     */
    public static function invalidateApiRowCache(?int $dropId = null): void
    {
        if ($dropId === null || $dropId <= 0) {
            return;
        }
        Yii::$app->cache->delete(static::apiRowCacheKey($dropId));
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        static::invalidateApiRowCache((int)$this->id);
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        static::invalidateApiRowCache((int)$this->id);
        parent::afterDelete();
    }

    /**
     * Один drop с imageOrig (TYPE_ORIG), с кэшем 5 мин — без лишних запросов из списков заданий/API.
     */
    public static function findOneCachedWithImageOrig(int $id): ?Drop
    {
        if ($id <= 0) {
            return null;
        }
        $cache = Yii::$app->cache;
        $key = static::apiRowCacheKey($id);
        $cached = $cache->get($key);
        if ($cached !== false && is_array($cached) && isset($cached['attrs']) && is_array($cached['attrs'])) {
            $m = new static();
            $m->setAttributes($cached['attrs'], false);
            $m->setIsNewRecord(false);
            $m->setOldAttributes($cached['attrs']);
            if (!empty($cached['imageOrigAttrs']) && is_array($cached['imageOrigAttrs'])) {
                $img = new DropImage();
                $img->setAttributes($cached['imageOrigAttrs'], false);
                $img->setIsNewRecord(false);
                $img->setOldAttributes($cached['imageOrigAttrs']);
                $m->populateRelation('imageOrig', $img);
            } else {
                $m->populateRelation('imageOrig', null);
            }
            return $m;
        }

        $m = static::find()->where(['id' => $id])->with('imageOrig')->one();
        if ($m === null) {
            return null;
        }
        $payload = [
            'attrs' => $m->getAttributes(),
            'imageOrigAttrs' => $m->imageOrig ? $m->imageOrig->getAttributes() : null,
        ];
        $cache->set($key, $payload, static::API_ROW_CACHE_TTL);
        return $m;
    }

    /**
     * Gets query for [[DropImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDropImages()
    {
        return $this->hasMany(DropImage::class, ['drop_id' => 'id']);
    }

    /**
     * Gets query for [[SubDrops]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSubDrops()
    {
        return $this->hasMany(DropDrop::class, ['parent_drop_id' => 'id']);
    }

    /**
     *
     * @return string
     */
    public function getPriceCeil()
    {
        return ceil($this->price);
    }

    /**
     * @return string
     */
    public function getPriceFormat()
    {
        return number_format($this->priceCeil, 0, '.', ' ');
    }

    /**
     *
     * @return string
     */
    public function getPriceMarket()
    {
        return ceil($this->priceCeil);
    }

    /**
     *
     * @return string
     */
    public function getCurrency()
    {
        return CurrencyHelper::default();
    }

    /**
     * Gets query for [ImageOrig].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig()
    {
        return $this->hasOne(DropImage::class, ['drop_id' => 'id'])
//            ->cache(300)
            ->andWhere(['type' => DropImage::TYPE_ORIG]);
    }

    /**
     * Gets query for [ImageOrig2].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig2()
    {
        return $this->hasOne(DropImage::class, ['drop_id' => 'id'])
//            ->cache(300)
            ->andWhere(['type' => DropImage::TYPE_ORIG_2]);
    }

    /**
     *
     * @return Drop[]
     */
    public static function getForMarket($mainBlock = false, $update = false)
    {
        $cacheKey = 'getForMarketDrop3_' . $mainBlock;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = Drop::find()
                      ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
                      ->andWhere(['show_main_block' => $mainBlock])
                      ->orderBy(['sort' => SORT_ASC])
                      ->with('dropImages', 'type')  // Добавляем кэшируемые связи
                      ->all();

        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

    /**
     * Gets query for [Type].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getType()
    {
        return $this->hasOne(DropType::class, ['id' => 'type_id']);
    }

    /**
     * Gets query for [Category].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    /**
     * Gets query for [DropStat].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDropStat()
    {
        return $this->hasMany(DropStat::class, ['drop_id' => 'id']);
    }

    public function getRealPrice($floating = true)
    {
        $floatingPricePercent = 0;
        if (!Yii::$app->user->isGuest) {
            $floatingPricePercent = Yii::$app->user->identity->getFloatingPricePercent($this);
        }
        $price = $this->price - ($this->price * $this->discount / 100);
        if ($floating && !Yii::$app->user->isGuest && $floatingPricePercent > 0) {
            $counts = Yii::$app->drop->getCountBuy(Yii::$app->user->id);
            if (!empty($counts[$this->id])) {
                for ($i = 0; $i < $counts[$this->id]; $i++) {
                    $price += $price * ($floatingPricePercent / 100);
                }
            }
        }

        return ceil($price);
    }

    /**
     * Сумма возврата на баланс за одну строку корзины (UserDrop), согласованная с маркет-покупкой.
     *
     * При покупке списывается getRealPrice(false) × quantity заказа, а user_drop.count = max(1, drop.count) × quantity
     * (drop.count — размер одной «покупки» в игре, напр. 10 батончиков за одну транзакцию).
     * Нельзя умножать цену на user_drop.count напрямую — получится завышение в drop.count раз.
     */
    public static function getRefundAmountForUserDropLine(UserDrop $userDrop, Drop $drop): int
    {
        $pricePerPurchaseUnit = (int) $drop->getRealPrice(false);
        $stackPerPurchaseUnit = max(1, (int) ($drop->count ?? 1));
        $userStackTotal = max(1, (int) $userDrop->count);

        $purchaseUnits = intdiv($userStackTotal, $stackPerPurchaseUnit);
        if ($userStackTotal % $stackPerPurchaseUnit !== 0) {
            Yii::warning(
                "Refund: UserDrop #{$userDrop->id} count={$userStackTotal} not multiple of drop #{$drop->id} count={$stackPerPurchaseUnit}",
                __METHOD__
            );
            $purchaseUnits = max(1, $purchaseUnits);
        }
        if ($purchaseUnits < 1) {
            $purchaseUnits = 1;
        }

        return $pricePerPurchaseUnit * $purchaseUnits;
    }

    /**
     * Gets query for [BoxDrop].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBoxDrop()
    {
        return $this->hasMany(BoxDrop::class, ['drop_id' => 'id'])
            ->joinWith('box b')
            ->andWhere(['NOT IN', 'b.type', [Box::TYPE_FREE]])
            ->andWhere(['b.status' => Box::STATUS_ACTIVE]);
    }

    public static function getList() {
        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->orderBy(['price' => SORT_ASC])
                     ->all();

        $result = [];
        foreach ($drops as $item) {
            $image = !empty($item->imageOrig) ? $item->imageOrig->getImagePubUrl() : null;
            $result[$item->id] = json_encode([
                                                 'name' => $item->name,
                                                 'image' => $image,
                                             ]);
        }
        return $result;
    }

    public static function searchJS() {
        return [
            'ajaxData' => new JsExpression('function(params) {return {q:params.term}; }'),
            'processResults' => new JsExpression('function (data, params) {return {results: data.items};}'),
            'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
            'templateResult' => new JsExpression("
                                function (item) {
                                    if (item.loading) {
                                        return item.text;
                                    }
                                    try {
                                        var markup = '<div class=\"drop-select-item\"><img class=\"kv-icon-image\" src=\"' + item.image + '\"/><div class=\"drop-select-item-content\">' + item.name + '</div></div>';
                                        return '<div style=\"overflow:hidden;\">' + markup + '</div>';
                                    } catch {
                                        return item.text;
                                    }
                                }
                            "),
            'templateSelection' => new JsExpression("
                                function (item) {
                                    try {
                                        var model = JSON.parse(item.text);
                                        return '<div class=\"drop-select-item\"><img class=\"kv-icon-image\" src=\"' + model.image + '\"/><div class=\"drop-select-item-content\">' + model.name + '</div></div>';
                                    } catch {
                                        return item.text;
                                    }
                                }
                            "),
        ];
    }

    public static function getDropList($all = false, $update = false) {
        $cacheKey = "Drops_6_getDropList_" . $all;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }
        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->andWhere('rust_id is not null')
                     ->orderBy(['sort' => SORT_ASC])
                     ->all();

        $items = [];
        foreach ($drops as $item) {
            $items[$item->id] = json_encode([
                                                'id' => $item->id,
                                                'name' => $item->name,
                                                'image' => $item->imageOrig->getImagePubUrl(),
                                            ]);
        }

        Yii::$app->cache->set($cacheKey, $items, 3*60);
        return $items;
    }

    public function blocked($serverId = null) {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        if (empty($serverId) && !empty(Yii::$app->user->identity->server)) {
            $serverId = Yii::$app->user->identity->server->id;
        }
        $blockedAt = DropBlocked::getBlocked($this->id, $serverId);
        return !empty($blockedAt) && strtotime($blockedAt) > time();
    }

    public function blockedTime() {
        return empty($this->blocked_at) ? false : strtotime($this->blocked_at);
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_ORIG) {
                return $item->getImagePubUrl();
            }
        }
        return null;
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image64() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_64) {
                return $item->getImagePubUrl();
            }
        }
        return null;
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image150() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_150) {
                return $item->getImagePubUrl();
            }
        }
        return null;
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image100() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_100) {
                return $item->getImagePubUrl();
            }
        }
        return null;
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function imageShop() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_ORIG) {
                return $item->getImagePubUrlShop();
            }
        }
        return null;
    }

    /**
     * Получить второй URL изображения.
     * Кэширует значение, чтобы избежать повторных запросов.
     */
    public function image2() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_ORIG_2) {
                return $item->getImagePubUrl();
            }
        }
        return $this->image();
    }

    public static function productsImages($update = false) {
        $cacheKey = 'Drop_productsImages';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->with('dropImages')
                     ->all();

        foreach ($drops as $item) {
            $result[$item->id] = [
                'full' => $item->image(),
                '64px' => $item->image64(),
                '100px' => $item->image100(),
                '150px' => $item->image150(),
            ];
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }

    public static function productsImagesShop($update = false) {
        $cacheKey = 'Drop_productsImagesShop2';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->with('dropImages')
                     ->all();

        foreach ($drops as $item) {
            $result[$item->id] = $item->imageShop();
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }

    /**
     * @param false $update
     *
     * @return Drop[]|false|mixed
     */
    public static function getDropListAll($update = false) {
        $cacheKey = 'Drop_getDropListAll2';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->with('dropImages')
                     ->all();

        foreach ($drops as $item) {
            $result[$item->id] = $item;
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }

    public static function updateCache() {
        Drop::productsImages(true);
        Statistics::productsImages(true);
        Statistics::productsNames(true);
        Sets::getSetsForMarket(true, true);
        Sets::getSetsForMarket(false, true);
        Select::getForMarket(true, true);
        Select::getForMarket(false, true);
        Drop::getForMarket(true, true);
        Drop::getForMarket(false, true);
        Category::getCategories(true, true);
        Category::getCategories(false, true);
        Drop::getDropListAll(true);
    }

    public function give($userId, $count, $parentId = null, $boxId = null, $setId = null) {
        // Для VIP товаров на серверах без доната выдаем сразу в user_vip без user_drop и без команды
        if ($this->drop_type === self::TYPE_VIP) {
            // Получаем текущий сервер игрока
            $user = \common\models\user\User::findOne($userId);
            $currentServer = $user ? $user->getCurrentServer() : null;
            
            // Если у игрока есть текущий сервер и у него нет доната (is_store = 0), выдаем VIP сразу
            if ($currentServer && $currentServer instanceof \common\models\servers\Servers && $currentServer->is_store == 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                UserVip::createOrExtend($userId, $expiresAt);
                $command = str_replace('%STEAMID%', $user->steam_id, $this->command);
                \common\models\rcon\RconTasks::execute($command);
                return;
            }
            
            // Если текущего сервера нет или у него есть донат, продолжаем стандартную логику через user_drop
        }
        
        // VIP товары на серверах с магазином и другие товары обрабатываются через user_drop
        // Логика выдачи VIP будет в ShopController::methodGived()
        
        // Загружаем subDrops, если они не загружены
        $subDrops = $this->subDrops;
        if ($subDrops === null) {
            $subDrops = $this->getSubDrops()->all();
        }
        
        // Для VIP товаров и других товаров без subDrops создаем запись в user_drop
        if (empty($subDrops) || (in_array($this->drop_type, [Drop::TYPE_SET]) && $this->full_only)) {
            // boxId остается null для обычных покупок в магазине (чтобы товары можно было возвращать)
            // box_id = 14 устанавливается явно только для заданий в TasksV2Controller
            
            if (in_array($this->rust_id, ['-2139580305'])) {
                for ($i = 0; $i < $count; $i++) {
                    $userDrop = UserDrop::createRecord($userId, $this->id, $boxId, $setId, UserDrop::STATUS_ACTIVE, false, 1, null, $parentId);
                    \Yii::$app->queueProcess->push(new BuyDropJob(['userDrop'  => $userDrop]));
                }
            } else {
                $userDrop = UserDrop::createRecord($userId, $this->id, $boxId, $setId, UserDrop::STATUS_ACTIVE, false, $count, null, $parentId);
                \Yii::$app->queueProcess->push(new BuyDropJob(['userDrop'  => $userDrop]));
            }
        } else {
            // Если есть subDrops, обрабатываем их рекурсивно
            foreach ($subDrops as $subDrop) {
                $subDrop->drop->give($userId, $subDrop->count, $this->id, $boxId, $setId);
            }
        }
    }

}
