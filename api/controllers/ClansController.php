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

class ClansController extends Controller
{

    public function actionList($ip, $port)
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $items = json_decode('[
  {
    "tag": "5hp",
    "update_at": "2025-07-03 13:00",
    "is_authorization_code_lock": false,
    "is_authorization_turrets": true,
    "is_authorization_air_defense": true,
    "users": [76561199670355029, 76561199789423224, 76561198032733861, 76561198394504608, 76561199517593518]
  },
  {
    "tag": "4ert",
    "update_at": "2025-07-01 13:00",
    "is_authorization_code_lock": true,
    "is_authorization_turrets": true,
    "is_authorization_air_defense": true,
    "users": [76561199148011505, 76561198147904870]
  }
]', 1);

        return $items;
    }

}
