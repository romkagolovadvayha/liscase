<?php

namespace common\models\map;

use common\components\proxy\ProxySettings;
use common\components\queue\process\CustomMapGenerateJob;
use common\models\servers\Servers;
use common\models\User;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "map".
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $mapId
 * @property string|null $link
 * @property int $seed
 * @property int $size
 * @property int $version
 * @property string|null $image_link
 * @property string|null $image_link_icons
 * @property string|null $created_at
 * @property int $votes
 * @property int $server_id
 * @property bool $is_archive
 * @property bool $is_staging
 *
 * @property-read int $totalVotes
 * @property-read int $userVotes
 *
 * @property Servers[] $servers
 * @property Servers $server
 * @property UserMap[] $userMaps
 */
class Map extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'map';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['seed', 'size', 'version'], 'required'],
            [['seed', 'size', 'version', 'votes', 'server_id'], 'integer'],
            [['created_at'], 'safe'],
            [['is_archive', 'is_staging'], 'boolean'],
            [['mapId', 'link', 'image_link', 'image_link_icons', 'name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mapId' => 'Map ID',
            'link' => 'Link',
            'seed' => 'Seed',
            'size' => 'Size',
            'version' => 'Version',
            'image_link' => 'Image Link',
            'image_link_icons' => 'Image Link Icons',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Servers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServers()
    {
        return $this->hasMany(Servers::class, ['map_id' => 'id']);
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Gets query for [[UserMaps]] (голоса пользователей).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserMaps()
    {
        return $this->hasMany(UserMap::class, ['map_id' => 'id']);
    }

    /**
     * Считает количество голосов за карту.
     *
     * @return int
     */
    public function getTotalVotes()
    {
        return UserMap::find()->where(['map_id' => $this->id, 'vote' => 1])->count();
    }

    public function voted() {
        $exist = UserMap::find()
            ->andWhere(['user_id' => Yii::$app->user->id])
            ->andWhere(['map_id' => $this->id])
            ->exists();
        if ($exist) {
            return false;
        }

        $userMap = new UserMap();
        $userMap->user_id = Yii::$app->user->id;
        $userMap->map_id = $this->id;
        $userMap->vote = 1;
        $userMap->created_at = date('Y-m-d H:i:s');

        if ($userMap->save()) {
            // Используем updateCounters для атомарного обновления
            $this->updateCounters(['votes' => 1]);
            return true;
        }

        return false;
    }

    public function unvoted() {
        $vote = UserMap::find()
                        ->andWhere(['user_id' => Yii::$app->user->id])
                        ->andWhere(['map_id' => $this->id])
                        ->one();
        if (empty($vote)) {
            return false;
        }

        if ($vote->delete()) {
            // Используем updateCounters для атомарного обновления
            $this->updateCounters(['votes' => -1]);
            return true;
        }

        return false;
    }

    /**
     * Считает количество голосов пользователя.
     *
     * @return int
     */
    public function getUserVotes()
    {
        return UserMap::find()->where(['map_id' => $this->id, 'vote' => 1])->count();
    }
    
    /**
     * Получает актуальное количество голосов из БД
     *
     * @return int
     */
    public function getVotes()
    {
        // Всегда берем актуальные данные из user_map
        return (int)UserMap::find()->where(['map_id' => $this->id, 'vote' => 1])->count();
    }

    /**
     * Удаляет карту и ее изображения.
     *
     * @return bool|int
     */
    public function archived()
    {
        $this->is_archive = true;
        $this->save();

        $exists = Servers::find()
                         ->andWhere(['map_id' => $this->id])
                         ->exists();
        $existsMap = Map::find()
                         ->andWhere(['seed' => $this->seed])
                         ->andWhere(['size' => $this->size])
                         ->andWhere(['is_archive' => false])
                         ->exists();
        if ($this->save() && !$exists && !$existsMap) {
            if (file_exists(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link}")) {
                unlink(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link}");
            }
            if (file_exists(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link_icons}")) {
                unlink(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link_icons}");
            }
        }

        return true;
    }

    public function getImage()
    {
        return "/uploads/maps/{$this->image_link_icons}";
    }

    public function getPreviewImage()
    {
        return "/uploads/maps/{$this->image_link}";
    }

    public function renderLike($view)
    {
        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;

        if (!empty($user)) {
            $exist = UserMap::find()->andWhere(['map_id' => $this->id])->andWhere(['user_id' => $user->id])->exists();
        } else {
            $exist = false;
        }

        return $view->render('@frontend/views/maps/like', [
            'model' => $this,
            'liked' => $exist
        ]);
    }

    public static function getMapsList($size = 0, $useCache = true) {
        $result = [];
        $cacheKey = 'MapsController_getMapsList3_' . $size;
        
        if ($useCache) {
            $cached = Yii::$app->cache->get($cacheKey);
            if (!empty($cached) && is_array($cached)) {
                $result = $cached;
            }
        }
        
        if (empty($result)) {
            for ($i = 0; $i < 20; $i++) {
                $staging = Yii::$app->settings->get('maps_staging') ? 'true' : 'false';
                $response = (clone \Yii::$app->curl)
                    ->setHeader('X-API-Key', Yii::$app->settings->get('maps_apiKey'))
                    ->setHeader('Content-Type', 'application/json')
                    ->setRawPostData(Map::getSearchQuery($size))
                    ->post('https://api.rustmaps.com/v4/maps/search?page=' . $i . '&staging=' . $staging . '&includeAllProtocols=false&customMaps=false');

                $response = json_decode($response, 1);

                if (!is_array($response) || ($response['meta']['statusCode'] ?? null) !== 200) {
                    Yii::warning("Map::getMapsList: Error parsing maps for size {$size}, page {$i}", __METHOD__);
                    continue;
                }

                if (!empty($response['data']) && is_array($response['data'])) {
                    $result = ArrayHelper::merge($result, $response['data']);
                }
                sleep(1);
            }
            
            if (!empty($result)) {
                Yii::$app->cache->set($cacheKey, $result, 60*60);
            }
        }

        shuffle($result);
        return $result;
    }

    public static function getCustomMapsList($size = 0) {
        $result = [];
        $cacheKey = 'MapsController_getCustomMapsList2_' . $size;
        if (Yii::$app->cache->get($cacheKey)) {
            //return null;
        }
        //for ($i = 0; $i < 20; $i++) {
            \Yii::$app->queueProcess->push(new CustomMapGenerateJob(['size'  => $size]));
        //}
        Yii::$app->cache->set($cacheKey, 1, 60*60);
        return null;
    }

    /**
     * Парсит карты
     * maps/parsing
     *
     * @throws \Exception
     */
    public static function actionParsing($size = 4250, $serverId = null, $count = 10)
    {
        $mapList = Map::getMapsList($size);
        $i = 0;
        foreach ($mapList as $item) {
            $exist = Map::find()
                        ->andWhere(['is_archive' => 0])
                        ->andWhere(['size' => $item['size']])
                        ->andWhere(['seed' => $item['seed']])
                        ->andWhere(['server_id' => $serverId])
                        ->exists();
            if ($exist) {
                continue;
            }
            if ($i >= $count) {
                break;
            }
            $siteResponse = (clone \Yii::$app->curl)->get($item['url']);
            preg_match('/"id":"(.*?)"/s', $siteResponse, $matches);
            $mapId = str_replace(["\n", "\r", " "], '', trim($matches[1]));
            preg_match('/"imageIconUrl":"(.*?)"/s', $siteResponse, $matches);
            $imageIconUrl = str_replace(["\n", "\r", " "], '', trim($matches[1]));
            preg_match('/"imageUrl":"(.*?)"/s', $siteResponse, $matches);
            $imageUrl = str_replace(["\n", "\r", " "], '', trim($matches[1]));
            preg_match('/"saveVersion":(.*?),/s', $siteResponse, $matches);
            $saveVersion = str_replace(["\n", "\r"], '', trim($matches[1]));

            $fileIconPathFileName = "{$item['size']}_{$item['seed']}.jpg";
            $filePathFileName = "{$item['size']}_{$item['seed']}_400x400.jpg";
            Map::uploadOld($imageIconUrl, $fileIconPathFileName, $filePathFileName);

            $model = new Map();
            $model->mapId = $mapId;
            $model->name = $item['size'] . "_" . $item['seed'];
            $model->is_staging = Yii::$app->settings->get('maps_staging');
            $model->link = $item['url'];
            $model->image_link = $filePathFileName;
            $model->image_link_icons = $fileIconPathFileName;
            $model->seed = $item['seed'];
            $model->size = $item['size'];
            $model->version = $saveVersion;
            $model->server_id = $serverId;
            $model->created_at = date('Y-m-d H:i:s');
            $model->save();

            sleep(1);
            $i++;
        }
    }

    public static function uploadOld($imageUrl, $filename, $previewFilename) {
        $filePath = \Yii::getAlias('@frontend/web') . "/uploads/maps/{$filename}";
        $previewfilePath = \Yii::getAlias('@frontend/web') . "/uploads/maps/{$previewFilename}";
        $curl = clone Yii::$app->curl;
        if (ProxySettings::isEnabled(ProxySettings::MAP)) {
            $curl
                ->setOption(CURLOPT_PROXY, '154.196.30.165:62742')
                ->setOption(CURLOPT_PROXYUSERPWD, 'XyQREbm5:AZ1zUkyc');
        } else {
            $curl
                ->setOption(CURLOPT_PROXY, '')
                ->setOption(CURLOPT_NOPROXY, '*');
        }
        $image = $curl->get($imageUrl);
        Map::watermarkOld($image, $filePath, $previewfilePath);
    }

    public static function watermarkOld($image, $filePath, $previewfilePath) {
        // Загружаем основное изображение
        $background = imagecreatefromstring($image);

        // Получаем путь к watermark изображению
        $watermarkPath = Yii::$app->settings->get('design_watemark');
        $watermarkFilePath = null;
        $isTempFile = false;
        
        // Проверяем, является ли watermark URL (S3 или другой)
        if (preg_match('#^https?://#i', $watermarkPath)) {
            // Это URL, нужно скачать во временный файл
            try {
                $tempDir = sys_get_temp_dir();
                $tempFilePath = $tempDir . '/' . uniqid('watermark_') . '.png';
                $watermarkContent = (clone Yii::$app->curl)->get($watermarkPath);
                file_put_contents($tempFilePath, $watermarkContent);
                $watermarkFilePath = $tempFilePath;
                $isTempFile = true;
            } catch (\Exception $e) {
                Yii::error('Map::watermarkOld: Failed to download watermark from URL: ' . $watermarkPath . '. Error: ' . $e->getMessage(), __METHOD__);
                die('Ошибка при загрузке накладываемого изображения: не удалось скачать watermark');
            }
        } else {
            // Это локальный путь
            $watermarkFilePath = \Yii::getAlias('@frontend/web') . $watermarkPath;
        }

        // Загружаем накладываемое изображение
        $overlay = imagecreatefrompng($watermarkFilePath); // для прозрачного изображения используем PNG

        // Проверка на ошибку при загрузке накладываемого изображения
        if (empty($overlay)) {
            if ($isTempFile) {
                @unlink($watermarkFilePath);
            }
            die('Ошибка при загрузке накладываемого изображения');
        }

        // Включаем поддержку альфа-канала (для PNG)
        imagealphablending($background, true); // Включаем смешивание (по умолчанию оно отключено)
        imagesavealpha($background, true); // Сохраняем альфа-канал для прозрачности

        // Включаем поддержку альфа-канала для накладываемого изображения
        imagealphablending($overlay, false); // Отключаем смешивание для накладываемого изображения
        imagesavealpha($overlay, false); // Сохраняем альфа-канал для накладываемого изображения

        // Получаем размеры основного изображения
        $bg_width = imagesx($background);
        $bg_height = imagesy($background);

        // Получаем размеры накладываемого изображения
        $overlay_width = imagesx($overlay);
        $overlay_height = imagesy($overlay);

        // Позиция наложения (можно выбрать любое положение, здесь верхний левый угол)
        $pos_x = ($bg_width - $overlay_width) / 2; // Центрируем по горизонтали
        $pos_y = 50; // Центрируем по вертикали

        // Накладываем одно изображение на другое с учетом прозрачности
        imagecopymerge($background, $overlay, $pos_x, $pos_y, 0, 0, $overlay_width, $overlay_height, 15);

        // Сохраняем результат в файл или выводим его в браузер
        //header('Content-Type: image/jpeg');
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }

        imagejpeg($background, $filePath);

        // Сжимаем изображение до 200x200
        $resize_width = 200;
        $resize_height = 200;
        $resized_image = imagecreatetruecolor($resize_width, $resize_height);

        // Устанавливаем прозрачность для нового изображения
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);

        // Масштабируем изображение
        imagecopyresampled($resized_image, $background, 0, 0, 0, 0, $resize_width, $resize_height, $bg_width, $bg_height);

        // Создаем каталог, если его нет
        if (!file_exists(dirname($previewfilePath))) {
            mkdir(dirname($previewfilePath), 0777, true);
        }

        imagejpeg($resized_image, $previewfilePath);

        // Освобождаем память
        imagedestroy($background);
        imagedestroy($overlay);
        imagedestroy($resized_image);
        
        // Удаляем временный файл watermark, если использовался
        if ($isTempFile && file_exists($watermarkFilePath)) {
            @unlink($watermarkFilePath);
        }
    }

    public static function upload($imageUrl, $filename, $depecate = null) {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) {
            throw new \InvalidArgumentException('Map image URL must be a valid HTTP(S) URL');
        }

        $filePath = \Yii::getAlias('@frontend/web') . "/uploads/maps/{$filename}";
        $attempts = [
            'direct' => [
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
            ],
        ];
        if (ProxySettings::isEnabled(ProxySettings::MAP)) {
            $attempts['proxy'] = [
                CURLOPT_PROXY => '154.196.30.165:62742',
                CURLOPT_PROXYUSERPWD => 'XyQREbm5:AZ1zUkyc',
            ];
        }
        $failures = [];

        foreach ($attempts as $attemptName => $options) {
            $curl = (clone Yii::$app->curl)
                ->setHeader('Accept', 'image/jpeg,image/png;q=0.9')
                ->setOption(CURLOPT_FOLLOWLOCATION, true)
                ->setOption(CURLOPT_MAXREDIRS, 5)
                ->setOption(CURLOPT_CONNECTTIMEOUT, 10)
                ->setOption(CURLOPT_TIMEOUT, 30)
                ->setOption(CURLOPT_ENCODING, '');

            foreach ($options as $option => $value) {
                $curl->setOption($option, $value);
            }

            try {
                $image = $curl->get($imageUrl);
            } catch (\Throwable $throwable) {
                $failures[] = $attemptName . ': request failed (' . $throwable->getMessage() . ')';
                continue;
            }

            $statusCode = (int)$curl->responseCode;
            $contentType = $curl->responseType ?: 'unknown';
            $contentLength = is_string($image) ? strlen($image) : 0;
            $imageInfo = $contentLength > 0 ? @getimagesizefromstring($image) : false;

            if ($statusCode < 200 || $statusCode >= 300 || $imageInfo === false) {
                $failures[] = sprintf(
                    '%s: HTTP %s, content-type %s, %d bytes',
                    $attemptName,
                    $curl->responseCode ?: 'unknown',
                    $contentType,
                    $contentLength
                );
                continue;
            }

            try {
                Map::watermark($image, $filePath);
                return $filePath;
            } catch (\Throwable $throwable) {
                $failures[] = $attemptName . ': image processing failed (' . $throwable->getMessage() . ')';
            }
        }

        $host = parse_url($imageUrl, PHP_URL_HOST) ?: 'unknown host';
        throw new \RuntimeException(
            'Unable to download a supported map image from ' . $host . '. Attempts: ' . implode('; ', $failures)
        );
    }

    public static function watermark($image, $filePath) {
        // Загружаем основное изображение
        $background = is_string($image) && $image !== '' ? @imagecreatefromstring($image) : false;
        if ($background === false) {
            throw new \RuntimeException('Downloaded map image is empty, invalid, or unsupported by GD');
        }

        // Получаем путь к watermark изображению
        $watermarkPath = Yii::$app->settings->get('design_watemark');
        $watermarkFilePath = null;
        $isTempFile = false;
        
        // Проверяем, является ли watermark URL (S3 или другой)
        if (preg_match('#^https?://#i', $watermarkPath)) {
            // Это URL, нужно скачать во временный файл
            try {
                $tempDir = sys_get_temp_dir();
                $tempFilePath = $tempDir . '/' . uniqid('watermark_') . '.png';
                $watermarkContent = (clone Yii::$app->curl)->get($watermarkPath);
                file_put_contents($tempFilePath, $watermarkContent);
                $watermarkFilePath = $tempFilePath;
                $isTempFile = true;
            } catch (\Exception $e) {
                Yii::error('Map::watermark: Failed to download watermark from URL: ' . $watermarkPath . '. Error: ' . $e->getMessage(), __METHOD__);
                die('Ошибка при загрузке накладываемого изображения: не удалось скачать watermark');
            }
        } else {
            // Это локальный путь
            $watermarkFilePath = \Yii::getAlias('@frontend/web') . $watermarkPath;
        }

        // Загружаем накладываемое изображение
        $overlay = imagecreatefrompng($watermarkFilePath); // для прозрачного изображения используем PNG

        // Проверка на ошибку при загрузке накладываемого изображения
        if (empty($overlay)) {
            if ($isTempFile) {
                @unlink($watermarkFilePath);
            }
            die('Ошибка при загрузке накладываемого изображения');
        }

        // Включаем поддержку альфа-канала (для PNG)
        imagealphablending($background, true); // Включаем смешивание (по умолчанию оно отключено)
        imagesavealpha($background, true); // Сохраняем альфа-канал для прозрачности

        // Включаем поддержку альфа-канала для накладываемого изображения
        imagealphablending($overlay, false); // Отключаем смешивание для накладываемого изображения
        imagesavealpha($overlay, false); // Сохраняем альфа-канал для накладываемого изображения

        // Получаем размеры основного изображения
        $bg_width = imagesx($background);
        $bg_height = imagesy($background);

        // Получаем размеры накладываемого изображения
        $overlay_width = imagesx($overlay);
        $overlay_height = imagesy($overlay);

        // Позиция наложения (можно выбрать любое положение, здесь верхний левый угол)
        $pos_x = ($bg_width - $overlay_width) / 2; // Центрируем по горизонтали
        $pos_y = 50; // Центрируем по вертикали

        // Накладываем одно изображение на другое с учетом прозрачности
        imagecopymerge($background, $overlay, $pos_x, $pos_y, 0, 0, $overlay_width, $overlay_height, 15);

        // Сохраняем результат в файл или выводим его в браузер
        //header('Content-Type: image/jpeg');
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }

        imagejpeg($background, $filePath);

        // Освобождаем память
        imagedestroy($background);
        imagedestroy($overlay);
        
        // Удаляем временный файл watermark, если использовался
        if ($isTempFile && file_exists($watermarkFilePath)) {
            @unlink($watermarkFilePath);
        }
    }

    public static function getSearchQuery($size) {
        if ($size >= 4250) {
            return '{
  "searchQuery": {
    "size": {
      "min": ' . $size . ',
      "max": ' . $size . '
    },
    "biomes": [
      {
        "type": "Snow",
        "settings": {
          "min": 0,
          "max": 100
        }
      },
      {
        "type": "Desert",
        "settings": {
          "min": 0,
          "max": 100
        }
      },
      {
        "type": "Forest",
        "settings": {
          "min": 0,
          "max": 100
        }
      },
      {
        "type": "Tundra",
        "settings": {
          "min": 0,
          "max": 100
        }
      }
    ],
    "monuments": {
      "min": 0,
      "max": 300
    },
    "largeMonuments": [
      {
        "type": "Airfield",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Bandit Town",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Ferry Terminal",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Outpost",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {"type":"Excavator","selectionStatus":"Wanted","requiredBiomes":[],"blockedBiomes":[]},
      {
        "type": "Junkyard",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Launch Site",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Large Harbor",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Military Tunnels",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Nuclear Missile Silo",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Powerplant",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Satellite Dish",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Sewer Branch",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Small Harbor",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Sphere Tank",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Trainyard",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Radtown",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Water Treatment",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      }
    ],
    "gasStations": {
      "min": 0,
      "max": 4
    },
    "supermarkets": {
      "min": 0,
      "max": 4
    },
    "warehouses": {
      "min": 0,
      "max": 4
    },
    "lighthouses": {
      "min": 0,
      "max": 4
    },
    "islands": {
      "min": 0,
      "max": 30
    },
    "landPercentageOfMap": {
      "min": 0,
      "max": 100
    },
    "caves": {
      "min": 0,
      "max": 20
    },
    "swamps": {
      "min": 0,
      "max": 5
    },
    "mountains": {
      "min": 0,
      "max": 3
    },
    "icebergs": {
      "min": 0,
      "max": 25
    },
    "iceLakes": {
      "min": 0,
      "max": 5
    },
    "rivers": {
      "min": 0,
      "max": 20
    },
    "waterWells": {
      "min": 0,
      "max": 10
    },
    "lakes": {
      "min": 0,
      "max": 10
    },
    "canyons": {
      "min": 0,
      "max": 10
    },
    "oases": {
      "min": 0,
      "max": 10
    },
    "buildableRocks": {
      "min": 0,
      "max": 50
    }
  }
}';
        }
        return '{
  "searchQuery": {
    "size": {
      "min": ' . $size . ',
      "max": ' . $size . '
    },
    "biomes": [
      {
        "type": "Snow",
        "settings": {
          "min": 0,
          "max": 100
        }
      },
      {
        "type": "Desert",
        "settings": {
          "min": 0,
          "max": 100
        }
      },
      {
        "type": "Forest",
        "settings": {
          "min": 0,
          "max": 100
        }
      },
      {
        "type": "Tundra",
        "settings": {
          "min": 0,
          "max": 100
        }
      }
    ],
    "monuments": {
      "min": 0,
      "max": 300
    },
    "largeMonuments": [
      {
        "type": "Airfield",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Bandit Town",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Ferry Terminal",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Outpost",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Excavator",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Junkyard",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Launch Site",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Large Harbor",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Military Tunnels",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Nuclear Missile Silo",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Powerplant",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Satellite Dish",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Sewer Branch",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Small Harbor",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Sphere Tank",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Trainyard",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Radtown",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      },
      {
        "type": "Water Treatment",
        "selectionStatus": "NoPreference",
        "requiredBiomes": [],
        "blockedBiomes": []
      }
    ],
    "gasStations": {
      "min": 0,
      "max": 4
    },
    "supermarkets": {
      "min": 0,
      "max": 4
    },
    "warehouses": {
      "min": 0,
      "max": 4
    },
    "lighthouses": {
      "min": 0,
      "max": 4
    },
    "islands": {
      "min": 0,
      "max": 30
    },
    "landPercentageOfMap": {
      "min": 0,
      "max": 100
    },
    "caves": {
      "min": 0,
      "max": 20
    },
    "swamps": {
      "min": 0,
      "max": 5
    },
    "mountains": {
      "min": 0,
      "max": 3
    },
    "icebergs": {
      "min": 0,
      "max": 25
    },
    "iceLakes": {
      "min": 0,
      "max": 5
    },
    "rivers": {
      "min": 0,
      "max": 20
    },
    "waterWells": {
      "min": 0,
      "max": 10
    },
    "lakes": {
      "min": 0,
      "max": 10
    },
    "canyons": {
      "min": 0,
      "max": 10
    },
    "oases": {
      "min": 0,
      "max": 10
    },
    "buildableRocks": {
      "min": 0,
      "max": 50
    }
  }
}';
    }
}
