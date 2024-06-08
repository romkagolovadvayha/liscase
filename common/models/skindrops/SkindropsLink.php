<?php

namespace common\models\skindrops;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $steam_id
 * @property string              $tradeurl
 * @property string              $partner
 * @property string              $token
 * @property User      $user
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


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['steam_id' => 'steam_id']);
    }

}
