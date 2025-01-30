<?php

namespace frontend\forms\user;

use common\components\queue\stats\UpdateReportJob;
use common\components\web\Cookie;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\user\User;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;
use yii\helpers\HtmlPurifier;

class ReportForm extends Reports
{
    public $text;

    /** @var User */
    private $_user;

    /** @var User */
    private $_recepient_user;

    /** @var Servers */
    private $_server;

    public function rules(): array
    {
        return [
            [['text'], 'required'],
            [['text'], 'trim'],
            [['text'], 'string', 'max' => 255],
        ];
    }

    public function setUser($user)
    {
        $this->_user = $user;
    }

    public function setRecepientUser($recepientUser)
    {
        $this->_recepient_user = $recepientUser;
    }

    public function setServer($server)
    {
        $this->_server = $server;
    }

    /**
     * @return bool|Promocode|\yii\db\ActiveRecord|null
     */
    public function saveRecord()
    {
        if (Yii::$app->user->isGuest) {
            $this->addError('code', Yii::t('common', 'Вам нужно авторизоваться на сайте!'));
            return null;
        }
        $server = $this->_server;

        $exist = Reports::find()
                              ->andWhere(['steam_id' => $this->_user->steam_id])
                              ->andWhere(['recepient_steam_id' => $this->_recepient_user->id])
                              ->andWhere(['wipe' => $server->currentWipe()])
                              ->exists();

        if ($exist) {
            $this->addError('code', Yii::t('common', 'Вы уже отправляли жалобу на данного игрока в этом вайпе!'));
            return null;
        }

        $item = [
            'steam_id' => $this->_user->steam_id,
            'recepient_steam_id' => $this->_recepient_user->steam_id,
            'reason' => HtmlPurifier::process($this->text),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        Yii::$app->queueReport->push(new UpdateReportJob([
                                                             'item' => $item,
                                                             'serverTag' => $server->tag,
                                                             'serverName' => $server->name,
                                                             'wipeDate' => $server->currentWipe(),
                                                             'wipe' => $server->wipe,
                                                         ]));

        return true;
    }

}
