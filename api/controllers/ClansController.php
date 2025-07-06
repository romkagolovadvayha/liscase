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

    public function actionList($ip = null, $port = null)
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
$d = date('Y-m-d H:i:s');
        $items = json_decode('[
  {
    "tag": "5hp",
    "color_tag": "#b2b2b2",
    "update_at": "' . $d. '",
    "users": [
      {
        "steam_id": "76561199670355029",
        "lock": true,
        "turrets": true,
        "defense": true,
        "cupboard_auth": false
      },
      {
        "steam_id": "76561198394504608",
        "lock": true,
        "turrets": true,
        "defense": true,
        "cupboard_auth": true
      }
    ]
  },
  {
    "tag": "4ert",
    "color_tag": "#b2b2b2",
    "update_at": "2025-07-01 13:00",
       "users": [
      {
        "steam_id": "76561198000000005",
        "lock": true,
        "turrets": true,
        "defense": false,
        "cupboard_auth": false
      },
      {
        "steam_id": "76561198000000006",
        "lock": false,
        "turrets": true,
        "defense": true,
        "cupboard_auth": true
      }
    ]
  }
]', 1);

        return $items;
    }

}
