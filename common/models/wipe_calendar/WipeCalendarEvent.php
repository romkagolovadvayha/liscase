<?php

namespace common\models\wipe_calendar;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property string $event_type map_wipe|global_wipe|game_update|custom
 * @property int|null $server_id
 * @property string|null $title
 * @property string $event_at
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read Servers|null $server
 */
class WipeCalendarEvent extends ActiveRecord
{
    public const TYPE_MAP_WIPE = 'map_wipe';
    public const TYPE_GLOBAL_WIPE = 'global_wipe';
    public const TYPE_GAME_UPDATE = 'game_update';
    public const TYPE_CUSTOM = 'custom';

    public static function tableName(): string
    {
        return '{{%wipe_calendar_event}}';
    }

    public static function typeList(): array
    {
        return [
            self::TYPE_MAP_WIPE => Yii::t('common', 'Вайп карты'),
            self::TYPE_GLOBAL_WIPE => Yii::t('common', 'Глобальный вайп'),
            self::TYPE_GAME_UPDATE => Yii::t('common', 'Обновление игры'),
            self::TYPE_CUSTOM => Yii::t('common', 'Другое событие'),
        ];
    }

    public function rules(): array
    {
        return [
            [['event_type', 'event_at'], 'required'],
            [['server_id'], 'integer'],
            [['title'], 'string', 'max' => 255],
            [['event_at', 'created_at', 'updated_at'], 'safe'],
            [
                'event_type',
                'in',
                'range' => array_keys(self::typeList()),
            ],
            [
                'title',
                'required',
                'when' => static function (self $m) {
                    return $m->event_type === self::TYPE_CUSTOM;
                },
            ],
            [
                'server_id',
                'required',
                'when' => static function (self $m) {
                    return $m->event_type === self::TYPE_MAP_WIPE
                        || $m->event_type === self::TYPE_GLOBAL_WIPE;
                },
            ],
            [
                'server_id',
                'exist',
                'skipOnError' => true,
                'targetClass' => Servers::class,
                'targetAttribute' => ['server_id' => 'id'],
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'event_type' => Yii::t('common', 'Тип'),
            'server_id' => Yii::t('common', 'Сервер'),
            'title' => Yii::t('common', 'Название'),
            'event_at' => Yii::t('common', 'Дата и время'),
        ];
    }

    public function getServer(): ActiveQuery
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getCalendarTitle(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title;
        }
        if (($this->event_type === self::TYPE_MAP_WIPE || $this->event_type === self::TYPE_GLOBAL_WIPE) && $this->server) {
            return $this->server->name;
        }

        return (string) (self::typeList()[$this->event_type] ?? $this->event_type);
    }

    public function getEventColorClass(): string
    {
        switch ($this->event_type) {
            case self::TYPE_MAP_WIPE:
                return 'wipe-cal-event--map';
            case self::TYPE_GLOBAL_WIPE:
                return 'wipe-cal-event--global';
            case self::TYPE_GAME_UPDATE:
                return 'wipe-cal-event--update';
            default:
                return 'wipe-cal-event--custom';
        }
    }
}
