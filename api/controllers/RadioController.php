<?php

namespace api\controllers;

use common\models\radio\RadioStation;
use yii\web\Controller;
use Yii;
use yii\web\Response;

class RadioController extends Controller
{

    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $list = [
          [
              'name' => 'Русское',
              'url' => 'https://rusradio.hostingradio.ru/rusradio128.mp3',
          ],
          [
              'name' => 'Маруся',
              'url' => 'https://radio-holding.ru:9433/marusya_default',
          ],
          [
              'name' => 'Новое',
              'url' => 'https://stream.newradio.ru/novoe96.aacp',
          ],
          [
              'name' => 'TNT',
              'url' => 'https://tntradio.hostingradio.ru:8027/tntradio128.mp3',
          ],
          [
              'name' => 'Авто',
              'url' => 'https://pub0201.101.ru/stream/air/aac/64/100',
          ],
          [
              'name' => 'Energy',
              'url' => 'https://pub0201.101.ru/stream/air/aac/64/99',
          ],
          [
              'name' => 'Попса',
              'url' => 'https://pub0201.101.ru/stream/air/aac/64/99',
          ],
          [
              'name' => 'Шансон',
              'url' => 'https://chanson.hostingradio.ru:8041/chanson128.mp3',
          ],
          [
              'name' => 'Романтический Шансон',
              'url' => 'https://chanson.hostingradio.ru:8041/chanson-romantic256.mp3',
          ],
          [
              'name' => 'Калина Красная',
              'url' => 'https://icecast-studio21.cdnvideo.ru/KalynaK_1a',
          ],
          [
              'name' => 'Спутник',
              'url' => 'https://radio.mediacdn.ru/sputnik_fm.mp3',
          ]
        ];

        // Получаем радиостанции из базы данных
        $dbStations = RadioStation::find()
            ->where(['status' => RadioStation::STATUS_ACTIVE])
            ->andWhere(['is_running' => 1])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        // Добавляем радиостанции из БД в конец списка
        foreach ($dbStations as $station) {
            $list[] = [
                'name' => $station->name,
                'url' => $station->getStreamUrl(),
            ];
        }

        $str = "";
        foreach ($list as $item) {
            $str .= ',' . $item['name'] . ',' . $item['url'];
        }

        return [
            'radioList' => substr($str, 1)
        ];
    }

}
