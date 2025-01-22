<?php

namespace console\controllers;

use common\models\map\Map;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\helpers\ArrayHelper;

class MapsController extends Controller
{
    /**
     * Парсит карты
     * maps/parsing
     *
     * @throws \Exception
     */
    public function actionParsing($size = 4250, $serverId = null, $count = 10)
    {
        $mapList = $this->getMapsList($size);
        $i = 0;
        foreach ($mapList as $item) {
            $exist = Map::find()
                ->andWhere(['is_archive' => 0])
                ->andWhere(['size' => $item['size']])
                ->andWhere(['seed' => $item['seed']])
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
            $this->upload($imageIconUrl, $fileIconPathFileName, $filePathFileName);

            $model = new Map();
            $model->mapId = $mapId;
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

    private function getMapsList($size = 0) {
        $result = [];
        $cacheKey = 'MapsController_getMapsList_' . $size;
        if (Yii::$app->cache->get($cacheKey)) {
            $result = Yii::$app->cache->get($cacheKey);
        }
        if (empty($result)) {
            for ($i = 0; $i < 20; $i++) {
                $response = (clone \Yii::$app->curl)
                    ->setHeader('X-API-Key', '03f6a4103d7d4820bed03f4322f72f26')
                    ->setHeader('Content-Type', 'application/json')
                    ->setRawPostData($this->getSearchQuery($size))
                    ->post('https://api.rustmaps.com/v4/maps/search?page=' . $i . '&staging=true&includeAllProtocols=false&customMaps=false');

                $response = json_decode($response, 1);

                if ($response['meta']['statusCode'] !== 200) {
                    Yii::$app->telegramChats->sendMessage('Ошибка парсинга карт.');
                    continue;
                }

                $result = ArrayHelper::merge($result, $response['data']);
                sleep(1);
            }
            Yii::$app->cache->set($cacheKey, $result, 60*60);
        }

        shuffle($result);
        return $result;
    }

    /**
     * maps/start
     */
    public function actionStart() {
        $date = new \DateTime();
        $date->modify('+4 day');

        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $sizes = [4250, 3750];

        foreach ($servers as $server) {
            /** @var Map[] $maps */
            $maps = Map::find()
                       ->andWhere(['server_id' => $server->id])
                       ->all();
            foreach ($maps as $map) {
                $map->archived();
            }
//            if (date('Y-m-d', strtotime($server->next_wipe)) !== $date->format('Y-m-d')) {
//                continue;
//
//            }
            $countSizes = 0;
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                $countSizes++;
            }
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                $this->actionParsing($size, $server->id, (int)(10 / $countSizes));
            }
        }
    }

    private function upload($imageUrl, $filename, $previewFilename) {
        $filePath = \Yii::getAlias('@frontend/web') . "/uploads/maps/{$filename}";
        $previewfilePath = \Yii::getAlias('@frontend/web') . "/uploads/maps/{$previewFilename}";
        $this->watermark(file_get_contents($imageUrl), $filePath, $previewfilePath);
//        file_put_contents($filePath, $image);
    }

    private function watermark($image, $filePath, $previewfilePath) {
        // Загружаем основное изображение
        $background = imagecreatefromstring($image);

        // Загружаем накладываемое изображение
        $overlay = imagecreatefrompng(\Yii::getAlias('@frontend/web') . '/images/watermark/maps.png'); // для прозрачного изображения используем PNG

        // Проверка на ошибку при загрузке накладываемого изображения
        if (empty($overlay)) {
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
        $resize_width = 400;
        $resize_height = 400;
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
    }

    private function getSearchQuery($size) {
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
