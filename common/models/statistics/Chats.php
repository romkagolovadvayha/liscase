<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $message
 * @property bool   $is_muted
 * @property string $created_at
 * @property string $server_tag
 */
class Chats extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'servers_chats';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'          => Yii::t('common', 'ID'),
            'steam_id'    => Yii::t('common', 'Steam ID'),
            'message'    => Yii::t('common', 'Сообщение'),
            'created_at'    => Yii::t('common', 'Дата'),
            'server_tag'    => Yii::t('common', 'Сервер'),
        ];
    }
}
