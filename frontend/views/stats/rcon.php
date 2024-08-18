<?php

/** @var yii\web\View $this */
/** @var Servers $server */

use yii\bootstrap5\Html;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;
use common\models\servers\Servers;

//$Query = new \xPaw\SourceQuery\SourceQuery();

$ip = '185.189.255.247';
$port = 36101;
$password = 'kuygqaji';
//try
//{
//    $Query->Connect( $ip, $port, 1, \xPaw\SourceQuery\SourceQuery::SOURCE );
//
//    $Query->SetRconPassword( $password );
//
//    var_dump( $Query->Rcon( 'o.plugins' ) );
//}
//catch( Exception $e )
//{
//    echo $e->getMessage( );
//}
//finally
//{
//    $Query->Disconnect( );
//}
//exit;


function send_command($ip,$port,$rcon,$command) {
    $fp = @fsockopen("udp://".$ip, $port, $errno, $errstr);
    if ($fp){
        $request = chr(1).chr(0).chr(242).chr(strlen($rcon)).$rcon.pack("S",strlen($command)).$command;
        fwrite($fp, $request);
    }
}
respond('Connecting to ' . $ip . ':' . $port . '...');
$rcon = new \common\components\base\RustRcon($ip, $port, $password);

$resp = $rcon->Send("o.plugins");
$resp2 = $rcon->Read();
respond($resp);
respond("===============================");
respond($resp2);

$rcon->disconnect();
function respond($str)
{
    echo date('Y-m-d H:i:s') . substr((string)microtime(), 1, 4) . ': ' . $str . PHP_EOL. "<br/>";
}
send_command('185.189.255.247', 36101, 'kuygqaji', 'o.plugins');
$rcon = new \common\components\base\Rcon('185.189.255.247', 36101, 'kuygqaji');
$response = $rcon->send('o.plugins', true);
print_r($response);
exit;
