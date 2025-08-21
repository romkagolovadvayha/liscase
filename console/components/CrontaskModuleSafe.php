<?php
/**
 * User: gofmana
 * Date: 11/24/15
 * Time: 5:17 PM
 */

namespace gofmanaa\crontask;


use console\components\CrontabSafe;
use gofmanaa\crontask\components\Crontab as BaseCrontab;
use Yii;
use yii\base\BootstrapInterface;
use yii\helpers\Inflector;


class CrontaskModuleSafe extends \gofmanaa\crontask\Module
{

    public function init()
    {
        if(is_null($this->cronGroup)){
            $this->cronGroup = Inflector::slug( Yii::$app->id .'-'. Yii::getAlias('@app'));
        } else {
            $this->cronGroup = Inflector::slug( $this->cronGroup );
        }
        $this->setComponents([
                                 $this->nameComponent => [
                                     'class'      => CrontabSafe::class,
                                     'filename'   => $this->fileName,
                                     'directory'  => $this->fileDir,
                                     'crontabPath'=> $this->crontabPath,
                                     'cronGroup'  => $this->cronGroup
                                 ],
                             ]);

        parent::init();


    }


}