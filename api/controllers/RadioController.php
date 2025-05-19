<?php

namespace api\controllers;

use common\components\queue\process\ActivatedDropJob;
use common\controllers\WebController;
use common\models\box\Drop;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\site\SiteSetting;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\box\DropBlocked;
use WebSocket\Client;
use yii\base\BaseObject;
use yii\web\Controller;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
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
          ],
          [
              'name' => 'PROSTOJ ONE',
              'url' => 'https://ws.prostoj.store/radio1/stream',
          ],
          [
              'name' => 'PROSTOJ TWO',
              'url' => 'https://myradio24.org/46527',
          ],
        ];

        $str = "";
        foreach ($list as $item) {
            $str .= ',' . $item['name'] . ',' . $item['url'];
        }

        return [
            'radioList' => substr($str, 1)
        ];
    }

}
