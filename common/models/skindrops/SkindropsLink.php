<?php

namespace common\models\skindrops;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $steam_id
 * @property string              $tradeurl
 * @property string              $partner
 * @property string              $token
 */
class SkindropsLink extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'skindrops_links';
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return Yii::$app->db;
    }


}
