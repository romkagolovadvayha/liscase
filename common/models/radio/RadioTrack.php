<?php

namespace common\models\radio;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "radio_track".
 *
 * @property int $id
 * @property int $radio_station_id
 * @property int $user_id
 * @property string $title
 * @property string|null $artist
 * @property string $filename
 * @property int|null $duration
 * @property int $status
 * @property int $likes
 * @property int $plays
 * @property string|null $created_at
 *
 * @property RadioStation $radioStation
 * @property User $user
 * @property RadioTrackLike[] $radioTrackLikes
 */
class RadioTrack extends \yii\db\ActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECT = 2;
    public const STATUS_WAIT = 3;

    public $audioFile;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'radio_track';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_REJECT => Yii::t('common', 'Отклонен'),
            self::STATUS_ACTIVE => Yii::t('common', 'Одобрен'),
            self::STATUS_WAIT   => Yii::t('common', 'На модерации'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['radio_station_id', 'user_id', 'title'], 'required'],
            [['filename'], 'required', 'on' => 'update'], // required only on update
            [['radio_station_id', 'user_id', 'duration', 'status', 'likes', 'plays'], 'integer'],
            [['created_at'], 'safe'],
            [['title', 'artist', 'filename'], 'string', 'max' => 255],
            [['title', 'artist'], 'filter', 'filter' => '\yii\helpers\HtmlPurifier::process'],
            [['radio_station_id'], 'exist', 'skipOnError' => true, 'targetClass' => RadioStation::class, 'targetAttribute' => ['radio_station_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['audioFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'mp3', 'maxSize' => 1024 * 1024 * 20, 'on' => 'create'], // 20MB max
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        // Дефолтный сценарий - все атрибуты доступны (для SearchModel)
        $scenarios[self::SCENARIO_DEFAULT] = array_keys($this->attributeLabels());
        // Сценарий создания
        $scenarios['create'] = ['radio_station_id', 'user_id', 'title', 'artist', 'status', 'audioFile'];
        // Сценарий обновления
        $scenarios['update'] = ['radio_station_id', 'user_id', 'title', 'artist', 'filename', 'duration', 'status', 'likes', 'plays'];
        return $scenarios;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'radio_station_id' => Yii::t('common', 'Радиостанция'),
            'user_id' => 'User ID',
            'title' => Yii::t('common', 'Название трека'),
            'artist' => Yii::t('common', 'Исполнитель'),
            'filename' => Yii::t('common', 'Файл'),
            'duration' => Yii::t('common', 'Длительность'),
            'status' => Yii::t('common', 'Статус'),
            'likes' => Yii::t('common', 'Лайки'),
            'plays' => Yii::t('common', 'Прослушивания'),
            'created_at' => Yii::t('common', 'Дата добавления'),
            'audioFile' => Yii::t('common', 'MP3 файл'),
        ];
    }

    /**
     * Gets query for [[RadioStation]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRadioStation()
    {
        return $this->hasOne(RadioStation::class, ['id' => 'radio_station_id']);
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
     * Gets query for [[RadioTrackLikes]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRadioTrackLikes()
    {
        return $this->hasMany(RadioTrackLike::class, ['radio_track_id' => 'id']);
    }

    /**
     * Get full file path
     *
     * @return string
     */
    public function getFilePath()
    {
        if (!$this->radioStation) {
            return '';
        }
        return $this->radioStation->getFolderPath() . '/' . $this->filename;
    }

    /**
     * Get web URL for file
     *
     * @return string
     */
    public function getFileUrl()
    {
        if (!$this->radioStation) {
            return '';
        }
        return $this->radioStation->getUploadUrl() . '/' . $this->filename;
    }

    /**
     * Format duration as MM:SS
     *
     * @return string
     */
    public function getFormattedDuration()
    {
        if (!$this->duration) {
            return '--:--';
        }
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Format passed time
     */
    public function passed($time_format = 'H:i', $month_format = 'd.m.Y', $year_format = 'd.m.Y')
    {
        $date = new \DateTime($this->created_at);
        $today = new \DateTime('now', $date->getTimezone());
        $yesterday = new \DateTime('-1 day', $date->getTimezone());

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
}

