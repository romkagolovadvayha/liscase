<?php

/**
 * Yii bootstrap file.
 * Used for enhanced IDE code auto completion.
 */
class Yii extends \yii\BaseYii
{
    /**
     * @var BaseApplication|WebApplication|ConsoleApplication the application instance
     */
    public static $app;
}

spl_autoload_register(['Yii', 'autoload'], true, true);
Yii::$classMap  = include(__DIR__ . '/vendor/yiisoft/yii2/classes.php');
Yii::$container = new yii\di\Container;

/**
 * Class BaseApplication
 * Used for properties that are identical for both WebApplication and ConsoleApplication
 *
 * @property yii\redis\Cache                               $cache
 * @property linslin\yii2\curl\Curl                        $curl
 * @property \common\components\web\User                   $user
 * @property \common\components\steam\MarketApi            $marketApi
 * @property \common\components\payments\Paypalych         $paypalych
 * @property \common\components\payments\Tome              $tomeApi
 * @property \common\components\rusttm\RustTm              $rustTm
 * @property \common\components\rusttm\CsGoMarket          $csGoMarket
 * @property \common\components\rustcheck\RustCheck        $rustCheck
 * @property \common\components\discord\Discord            $discord
 * @property \common\components\payments\FreeKassa         $freeKassaApi
 * @property \common\components\payments\AnyPay            $anyPayApi
 * @property \common\components\telegram\TelegramPayments  $telegramPayments
 * @property \common\components\telegram\TelegramReports   $telegramReports
 * @property \common\components\telegram\TelegramChats     $telegramChats
 * @property \common\components\telegram\TelegramSupport   $telegramSupport
 * @property \common\components\telegram\TelegramReport    $telegramReport
 * @property \common\components\wargm\WarGM                $wargm
 * @property \common\components\openAi\OpenAiApi           $openAi
 * @property \common\components\midjourney\MidjourneyApi   $midjourney
 * @property \yii\queue\redis\Queue                        $queueOpenAi
 * @property \yii\queue\redis\Queue                        $queueStats
 * @property \yii\queue\redis\Queue                        $queueRaid
 * @property \yii\queue\redis\Queue                        $queueOnline
 * @property \yii\queue\redis\Queue                        $queueTop
 * @property \yii\queue\redis\Queue                        $queueReport
 * @property \yii\queue\redis\Queue                        $queueTeam
 * @property \yii\queue\redis\Queue                        $queueKills
 * @property \yii\queue\redis\Queue                        $queueParams
 * @property \yii\queue\redis\Queue                        $queueTelegram
 * @property \yii\queue\redis\Queue                        $queueMidjourney
 * @property \yii\queue\redis\Queue                        $queueProcess
 * @property \yii\queue\redis\Queue                        $queueSupport
 * @property \common\components\openAi\OpenAiSupport       $openAiSupport
 * @property \common\components\telegram\TelegramApiHelper $personalBotTelegram
 * @property \common\components\telegram\TelegramApiHelper $rustotekaBotTelegram
 * @property \common\components\storage\S3Api              $s3Api
 * @property \common\components\settings\Settings          $settings
 */
abstract class BaseApplication extends yii\base\Application
{
}

/**
 * Class WebApplication
 * Include only Web application related components here
 *
 */
class WebApplication extends yii\web\Application
{
}

/**
 * Class ConsoleApplication
 * Include only Console application related components here
 *
 */
class ConsoleApplication extends yii\console\Application
{
}