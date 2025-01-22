<?php

namespace common\models\building;

use common\models\servers\Servers;
use common\models\user\User;
use Leafo\ScssPhp\Server;
use Yii;

/**
 * This is the model class for table "building".
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $location
 * @property int $status
 * @property int $likes
 * @property string $wipe
 * @property string|null $server_tag
 * @property string|null $created_at
 *
 * @property BuildingLike[] $buildingLikes
 * @property BuildingImage[] $buildingImage
 * @property BuildingResident[] $buildingResident
 * @property User $user
 * @property Servers $server
 */
class Building extends \yii\db\ActiveRecord
{

    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECT = 2;
    public const STATUS_WAIT = 3;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'building';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_REJECT       => Yii::t('common', 'Отклонен'),
            self::STATUS_ACTIVE      => Yii::t('common', 'Активен'),
            self::STATUS_WAIT      => Yii::t('common', 'На модерации'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'status', 'name', 'description', 'location', 'server_tag'], 'required'],
            [['user_id', 'status', 'likes'], 'integer'],
            [['created_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 512],
            [['location'], 'string', 'max' => 3],
            [['server_tag'], 'string', 'max' => 11],
            [['name', 'description', 'location'], 'filter', 'filter' => '\yii\helpers\HtmlPurifier::process'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['server_tag'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_tag' => 'tag']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'name' => Yii::t('common', 'Название базы'),
            'description' => Yii::t('common', 'Краткое описание'),
            'location' => Yii::t('common', 'Квадрат расположения базы (Например: E14)'),
            'status' => Yii::t('common', 'Статус'),
            'server_tag' => Yii::t('common', 'Сервер'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * Gets query for [[BuildingLikes]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBuildingLikes()
    {
        return $this->hasMany(BuildingLike::class, ['building_id' => 'id']);
    }

    /**
     * Gets query for [[BuildingImage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBuildingImage()
    {
        return $this->hasMany(BuildingImage::class, ['building_id' => 'id']);
    }

    /**
     * Gets query for [[BuildingResident]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBuildingResident()
    {
        return $this->hasMany(BuildingResident::class, ['building_id' => 'id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[Servers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['tag' => 'server_tag']);
    }

    public function passed($time_format = 'H:i', $month_format = 'd.m.Y', $year_format = 'd.m.Y') {
        $date = new \DateTime($this->created_at);
        $today = new \DateTime('now', $date->getTimezone());
        $yesterday = new \DateTime('-1 day', $date->getTimezone());
        $tomorrow = new \DateTime('+1 day', $date->getTimezone());

        if ($today->format('ymd') == $date->format('ymd')) {
            return Yii::t('common', 'Сегодня');

        } elseif ($yesterday->format('ymd') == $date->format('ymd')) {
            return Yii::t('common', 'Вчера');

        } elseif ($today->format('Y') == $date->format('Y')) {
            return $date->format($month_format);
        } else {
            return $date->format($year_format);
        }
    }

    public function getLink() {
        return '/buildings/view?id=' . $this->id;
    }

    /**
     * @param User $user
     */
    public static function getBuildings($user) {
        /** @var Building[] $buildings */
        $buildings = Building::find()
            ->alias('b')
            ->joinWith('buildingResident br')
            ->andWhere(['br.user_id' => $user->id])
            ->andWhere(['b.status' => Building::STATUS_ACTIVE])
            ->orderBy(['b.id' => SORT_DESC])
            ->limit(3)
            ->all();

        $items = [];
        foreach ($buildings as $building) {
            $items[] = [
                'NAME' => $building->name,
                'IMAGE' => $building->buildingImage[0]->getPublicUrl(),
                'DATE' => $building->passed(),
                'URL' => $building->getLink(),
            ];
        }

        return $items;
    }
}
