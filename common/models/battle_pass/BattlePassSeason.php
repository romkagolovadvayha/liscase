<?php

namespace common\models\battle_pass;

use common\components\base\ActiveRecord;
use common\models\box\Drop;
use common\models\medals\Medal;
use common\models\tasks_v2\TaskV2;
use Yii;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $season_number
 * @property string|null $description
 * @property string $starts_at
 * @property string|null $ends_at
 * @property string $status
 * @property string $reward_type
 * @property int|null $reward_item_id
 * @property string|null $reward_currency
 * @property float|null $reward_amount
 * @property int|null $medal_id
 */
class BattlePassSeason extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FINISHED = 'finished';

    public static function tableName()
    {
        return 'battle_pass_season';
    }

    public function rules()
    {
        return [
            [['name', 'slug', 'season_number', 'starts_at', 'status', 'reward_type'], 'required'],
            [['season_number', 'reward_item_id', 'medal_id'], 'integer'],
            [['description'], 'string'],
            [['starts_at', 'ends_at'], 'safe'],
            [['reward_amount'], 'number'],
            [['name'], 'string', 'max' => 255],
            [['slug'], 'string', 'max' => 128],
            [['slug'], 'unique'],
            [['reward_currency'], 'string', 'max' => 50],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_FINISHED]],
            [['reward_type'], 'in', 'range' => [TaskV2::REWARD_TYPE_ITEM, TaskV2::REWARD_TYPE_CURRENCY]],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => Yii::t('common', 'Название'),
            'slug' => Yii::t('common', 'Slug'),
            'season_number' => Yii::t('common', 'Номер сезона'),
            'description' => Yii::t('common', 'Описание'),
            'starts_at' => Yii::t('common', 'Начало'),
            'ends_at' => Yii::t('common', 'Окончание'),
            'status' => Yii::t('common', 'Статус'),
            'reward_type' => Yii::t('common', 'Финальная награда'),
            'reward_item_id' => Yii::t('common', 'Предмет финальной награды'),
            'reward_currency' => Yii::t('common', 'Баланс'),
            'reward_amount' => Yii::t('common', 'Количество'),
            'medal_id' => Yii::t('common', 'Медаль'),
        ];
    }

    public function getTasks()
    {
        return $this->hasMany(TaskV2::class, ['battle_pass_season_id' => 'id'])
            ->orderBy(['battle_pass_position' => SORT_ASC]);
    }

    public function getRewardItem()
    {
        return $this->hasOne(Drop::class, ['id' => 'reward_item_id']);
    }

    public function getMedal()
    {
        return $this->hasOne(Medal::class, ['id' => 'medal_id']);
    }

    public function getRewardDropCached(): ?Drop
    {
        return $this->reward_item_id ? Drop::findOneCachedWithImageOrig((int)$this->reward_item_id) : null;
    }

    public static function findActive(): ?self
    {
        $now = date('Y-m-d H:i:s');
        return static::find()
            ->where(['status' => self::STATUS_ACTIVE])
            ->andWhere(['<=', 'starts_at', $now])
            ->andWhere(['or', ['ends_at' => null], ['>=', 'ends_at', $now]])
            ->orderBy(['season_number' => SORT_DESC])
            ->one();
    }

    public static function getStatusList(): array
    {
        return [
            self::STATUS_DRAFT => Yii::t('common', 'Черновик'),
            self::STATUS_ACTIVE => Yii::t('common', 'Активен'),
            self::STATUS_FINISHED => Yii::t('common', 'Завершён'),
        ];
    }
}
