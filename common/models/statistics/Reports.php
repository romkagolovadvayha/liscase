<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property string|null $event_id
 * @property string $steam_id
 * @property string $recepient_steam_id
 * @property string $reason
 * @property string $created_at
 * @property string $server_tag
 * @property string $wipe
 *
 * @property User        $user
 */
class Reports extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'servers_reports';
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['steam_id' => 'recepient_steam_id']);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'          => Yii::t('common', 'ID'),
            'steam_id'    => Yii::t('common', 'Steam ID'),
            'recepient_steam_id'    => Yii::t('common', 'На кого жалоба'),
            'reason'    => Yii::t('common', 'Причина'),
            'created_at'    => Yii::t('common', 'Дата'),
            'server_tag'    => Yii::t('common', 'Сервер'),
            'wipe'    => Yii::t('common', 'Wipe'),
        ];
    }
}
