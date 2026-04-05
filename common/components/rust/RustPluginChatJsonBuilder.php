<?php

namespace common\components\rust;

use common\models\servers\Servers;
use Yii;

/**
 * Плоский JSON для плагинов Rust (wipe / welcome / help), как у frontend\ApiController.
 */
final class RustPluginChatJsonBuilder
{
    public static function wipeInfo(string $serverTag): string
    {
        $color = Yii::$app->settings->get('colors_server-command');
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = 'Данных нет';
            $result['result'] = 'fail';
            $result['code'] = 104;
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $lastWipe = (new \DateTime($server->wipe))->format('d.m.Y H:i');
        $nextWipe = (new \DateTime($server->next_wipe))->format('d.m.Y H:i');
        $result['ru'] = "Последний вайп: <color={$color}>{$lastWipe} МСК</color>\nСледующий вайп: <color={$color}>{$nextWipe} МСК</color>";
        $result['en'] = "Last WIPE: <color={$color}>{$lastWipe} MSK</color>\nNext WIPE: <color={$color}>{$nextWipe} MSK</color>";
        $result['code'] = 200;
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public static function welcomeMessage(string $serverTag): string
    {
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = 'Данных нет';
            $result['result'] = 'fail';
            $result['code'] = 104;
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $color = Yii::$app->settings->get('colors_server-command');
        $colorPrimary = Yii::$app->settings->get('colors_server-command-primary');
        $result['ru'] = "Добро пожаловать на сервер {0}!" . PHP_EOL;
        $result['ru'] .= "<color={$color}><size=18>{$server->name}</size></color>" . PHP_EOL;
        $result['ru'] .= "Для получения информации о командах на сервере введите в чат <color={$color}>/help</color>" . PHP_EOL;
        $result['ru'] .= "Правила сервера и новости можно посмотреть в нашем Discord - <color={$colorPrimary}>" . Yii::$app->params['discordText'] . '</color>' . PHP_EOL;
        $result['ru'] .= 'Удачного выживания!';

        $nameEn = Yii::t('database', $server->name, [], 'en-US');
        $result['en'] = "Welcome to the server {0}!" . PHP_EOL;
        $result['en'] .= "<color={$color}><size=18>{$nameEn}</size></color>" . PHP_EOL;
        $result['en'] .= "To get information about commands on the server, enter into chat <color={$color}>/help</color>" . PHP_EOL;
        $result['en'] .= "Server rules and news can be found on our website - <color={$colorPrimary}>en." . Yii::$app->settings->get('site_domain') . '</color>' . PHP_EOL;
        $result['en'] .= 'Happy survival!';
        $result['code'] = 200;
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public static function helpInfo(string $serverTag): string
    {
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        $result = [];
        if (empty($server)) {
            $result['message'] = 'Данных нет';
            $result['result'] = 'fail';
            $result['code'] = 104;
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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

        $commands = json_decode($server->commands, true);
        if (!is_array($commands)) {
            $commands = [];
        }
        if (in_array('remove', $commands, true)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/remove</color> - Удаление обьектов";
            $result['en'] .= PHP_EOL . "<color={$color}>/remove</color> - Remove objects";
        }
        if (in_array('xrates', $commands, true)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/rate</color> - Смотреть текущие рейты";
            $result['en'] .= PHP_EOL . "<color={$color}>/rate</color> - Current rates";
        }
        if (in_array('fmenu', $commands, true)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/fmenu</color> - Меню друзей";
            $result['en'] .= PHP_EOL . "<color={$color}>/fmenu</color> - Friends menu";
        }
        if (in_array('sil', $commands, true)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/sil URL</color> - Вставить изображение в рамку";
            $result['en'] .= PHP_EOL . "<color={$color}>/sil URL</color> - Paste image";
        }
        if (in_array('vlock', $commands, true)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/vlock</color> - Установить код на транспорт";
            $result['en'] .= PHP_EOL . "<color={$color}>/vlock</color> - Codelock for minicopter";
        }
        if (in_array('store', $commands, true)) {
            $result['ru'] .= PHP_EOL . "<color={$color}>/store</color> - Корзина сервера";
            $result['en'] .= PHP_EOL . "<color={$color}>/store</color> - Basket server";
        }

        $result['ru'] .= PHP_EOL . PHP_EOL;

        if (!empty(Yii::$app->params['discordText'])) {
            $result['ru'] .= 'Discord: <color=' . $colorPrimary . '>' . Yii::$app->params['discordText'] . '</color>' . PHP_EOL;
        } else {
            $result['ru'] .= 'VK: <color=' . $colorPrimary . '>' . Yii::$app->params['vkText'] . '</color>' . PHP_EOL;
        }

        $result['ru'] .= 'Сайт: <color=' . $colorPrimary . '>' . Yii::$app->settings->get('site_domain') . '</color>';

        $result['en'] .= PHP_EOL . PHP_EOL;

        if (!empty(Yii::$app->params['discordText'])) {
            $result['en'] .= 'Discord: <color=' . $colorPrimary . '>' . Yii::$app->params['discordText'] . '</color>' . PHP_EOL;
        } else {
            $result['en'] .= 'VK: <color=' . $colorPrimary . '>' . Yii::$app->params['vkText'] . '</color>' . PHP_EOL;
        }

        $result['en'] .= 'Site: <color=' . $colorPrimary . '>en.' . Yii::$app->settings->get('site_domain') . '</color>';

        $result['code'] = 200;
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
