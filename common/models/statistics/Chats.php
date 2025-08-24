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

    public static function muteReason()
    {
        return [
          1 => [
              'reason' => 'Оскорбление родителей',
              'term' => 24 * 60,
          ],
          2 => [
              'reason' => 'Оскорбление администрации',
              'term' => 24 * 60,
          ],
        ];
    }

    public static function mute($data)
    {
        if (empty($data['steam_id'])) {
            return false;
        }
        if (empty($data['type'])) {
            return false;
        }
        if (empty($data['message'])) {
            return false;
        }

        $reasons = self::muteReason();
        if (empty($reasons[$data['type']])) {
            return false;
        }
        $reasonData = $reasons[$data['type']];
        $hour = $reasonData['term']/60;
        \Yii::$app->telegramChats->sendMessage("Мут игроку {$reasonData['steam_id']} за сообщение \"{$reasonData['message']}\" на {$hour} часа");

        return true;
    }
}
