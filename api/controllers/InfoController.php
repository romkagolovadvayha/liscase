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

class InfoController extends Controller
{

    public function actionWipe($serverTag)
    {
        header('Content-type: application/json');
        $color = Yii::$app->settings->get('colors_server-command');
        /** @var Servers $server */
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = "Данных нет";
            $result['result'] = "fail";
            $result['code'] = 104;
            return json_encode($result,JSON_PRETTY_PRINT);
        }
        $lastWipe = (new \DateTime($server->wipe))->format('d.m.Y H:i');
        $nextWipe = (new \DateTime($server->next_wipe))->format('d.m.Y H:i');
        $result['ru'] = "Последний вайп: <color={$color}>{$lastWipe} МСК</color>\nСледующий вайп: <color={$color}>{$nextWipe} МСК</color>";
        $result['en'] = "Last WIPE: <color={$color}>{$lastWipe} MSK</color>\nNext WIPE: <color={$color}>{$nextWipe} MSK</color>";
        $result['code'] = 200;
        return json_encode($result,JSON_PRETTY_PRINT);
    }

    public function actionWelcomeMessage($serverTag)
    {
        header('Content-type: application/json');
        /** @var Servers $server */
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = "Данных нет";
            $result['result'] = "fail";
            $result['code'] = 104;
            return json_encode($result,JSON_PRETTY_PRINT);
        }
        $color = Yii::$app->settings->get('colors_server-command');
        $colorPrimary = Yii::$app->settings->get('colors_server-command-primary');
        $result['ru'] = "Добро пожаловать на сервер {0}!" . PHP_EOL;
        $result['ru'] .= "<color={$color}><size=18>{$server->name}</size></color>" . PHP_EOL;
        $result['ru'] .= "Для получения информации о командах на сервере введите в чат <color={$color}>/help</color>" . PHP_EOL;
        $result['ru'] .= "Правила сервера и новости можно посмотреть в нашем Discord - <color={$colorPrimary}>" . Yii::$app->params['discordText'] . "</color>" . PHP_EOL;
        $result['ru'] .= "Удачного выживания!";

        $nameEn = Yii::t('database', $server->name, [], 'en-US');
        $result['en'] = "Welcome to the server {0}!" . PHP_EOL;
        $result['en'] .= "<color={$color}><size=18>{$nameEn}</size></color>" . PHP_EOL;
        $result['en'] .= "To get information about commands on the server, enter into chat <color={$color}>/help</color>" . PHP_EOL;
        $result['en'] .= "Server rules and news can be found on our website - <color={$colorPrimary}>en." . Yii::$app->settings->get('site_domain') . "</color>" . PHP_EOL;
        $result['en'] .= "Happy survival!";
        $result['code'] = 200;
        return json_encode($result,JSON_PRETTY_PRINT);
    }

    public function actionHelpInfo($serverTag)
    {
        header('Content-type: application/json');
        /** @var Servers $server */
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = "Данных нет";
            $result['result'] = "fail";
            $result['code'] = 104;
            return json_encode($result,JSON_PRETTY_PRINT);
        }
        $color = Yii::$app->settings->get('colors_server-command');
        $colorPrimary = Yii::$app->settings->get('colors_server-command-primary');
        $result['ru'] = "<color={$color}>/pop</color> - Текущий онлайн игроков" . PHP_EOL .
        "<color={$color}>/wipe</color> - Информация о вайпе" . PHP_EOL .
        "<color={$color}>/time</color> - Текущее время на сервере" . PHP_EOL .
        "<color={$color}>/pm</color> - Отправить личное сообщение пользователю";

        $result['en'] = "<color={$color}>/pop</color> - Current online for server" . PHP_EOL .
        "<color={$color}>/wipe</color> - Wipe info" . PHP_EOL .
        "<color={$color}>/time</color> - Current time server" . PHP_EOL .
        "<color={$color}>/pm</color> - Private message";

        $commands = $server->getCommandsList();
        if (in_array('remove', $commands)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/remove</color> - Удаление обьектов";
            $result['en'] .= PHP_EOL . "<color={$color}>/remove</color> - Remove objects";
        }
        if (in_array('xrates', $commands)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/rate</color> - Смотреть текущие рейты";
            $result['en'] .= PHP_EOL . "<color={$color}>/rate</color> - Current rates";
        }
        if (in_array('fmenu', $commands)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/fmenu</color> - Меню друзей";
            $result['en'] .= PHP_EOL . "<color={$color}>/fmenu</color> - Friends menu";
        }
        if (in_array('sil', $commands)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/sil URL</color> - Вставить изображение в рамку";
            $result['en'] .= PHP_EOL . "<color={$color}>/sil URL</color> - Paste image";
        }
        if (in_array('vlock', $commands)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/vlock</color> - Установить код на транспорт";
            $result['en'] .= PHP_EOL . "<color={$color}>/vlock</color> - Codelock for minicopter";
        }
        if (in_array('store', $commands)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/store</color> - Корзина сервера";
            $result['en'] .= PHP_EOL . "<color={$color}>/store</color> - Basket server";
        }

        $result['ru'] .= PHP_EOL . PHP_EOL;

        if (!empty(Yii::$app->params['discordText'])) {
            $result['ru'] .= "Discord: <color={$colorPrimary}>" . Yii::$app->params['discordText'] . "</color>" . PHP_EOL;
        } else {
            $result['ru'] .= "VK: <color={$colorPrimary}>" . Yii::$app->params['vkText'] . "</color>" . PHP_EOL;
        }

        $result['ru'] .= "Сайт: <color={$colorPrimary}>" . Yii::$app->settings->get('site_domain') . "</color>";

        $result['en'] .= PHP_EOL . PHP_EOL;

        if (!empty(Yii::$app->params['discordText'])) {
            $result['en'] .= "Discord: <color={$colorPrimary}>" . Yii::$app->params['discordText'] . "</color>" . PHP_EOL;
        } else {
            $result['en'] .= "VK: <color={$colorPrimary}>" . Yii::$app->params['vkText'] . "</color>" . PHP_EOL;
        }

        $result['en'] .= "Site: <color={$colorPrimary}>en." . Yii::$app->settings->get('site_domain') . "</color>";

        $result['code'] = 200;
        return json_encode($result,JSON_PRETTY_PRINT);
    }

    public function actionItems() {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Drop[] $list */
        $list = Drop::find()
            ->cache(60)
            ->andWhere(['<>', 'eng_name', ''])
            ->all();

        $items = [];
        foreach ($list as $item) {
            $categoryName = null;
            if (!empty($item->category)) {
                $categoryName = $item->category->name;
            }
            $items[] = [
              'name' => $item->name,
              'description' => $item->description,
              'eng_name' => $item->eng_name,
              'image' => $item->image(),
              'rust_id' => $item->rust_id,
              'type_id' => $item->type_id,
              'category_id' => $item->category_id,
              'category_name' => $categoryName,
              'blocked_hour' => $item->blocked_hour,
            ];
        }

        return $items;
    }

    public function actionSettings() {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var SiteSetting[] $list */
        $list = SiteSetting::find()
            ->cache(60)
            ->all();

        $items = [];
        foreach ($list as $item) {
            if ($item->type === 'password') {
                continue;
            }
            $items[] = [
              'name' => $item->name,
              'code' => $item->code,
              'category' => $item->category,
              'type' => $item->type,
              'system_code' => $item->category . "_" . $item->code,
              'is_translate' => $item->is_translate,
            ];
        }

        return $items;
    }

    public function actionServers() {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers[] $list */
        $list = Servers::find()
            ->cache(60)
            ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $items = [];
        foreach ($list as $item) {
            $items[] = [
              'name' => $item->name,
              'ip' => $item->ip,
              'text_ip' => $item->text_ip,
              'port' => $item->port,
              'query' => $item->query,
            ];
        }

        return $items;
    }

    /**
     * Получить значение настройки по ключу
     * @param string $key Ключ настройки (например, openAi_channelIds)
     * @return array
     */
    public function actionSetting($key) {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (empty($key)) {
            return [
                'success' => false,
                'message' => 'Необходимо указать ключ настройки'
            ];
        }

        $value = Yii::$app->settings->get($key);

        return [
            'success' => true,
            'key' => $key,
            'value' => $value
        ];
    }
}
