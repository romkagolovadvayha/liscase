<?php

namespace common\models\radio;

use Yii;

/**
 * This is the model class for table "radio_station".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $port
 * @property string $folder_name
 * @property int $status
 * @property int $is_running
 * @property int|null $current_track_id
 * @property int $listeners_count
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property RadioTrack $currentTrack
 * @property RadioTrack[] $radioTracks
 */
class RadioStation extends \yii\db\ActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'radio_station';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_INACTIVE => Yii::t('common', 'Неактивна'),
            self::STATUS_ACTIVE   => Yii::t('common', 'Активна'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'port', 'folder_name'], 'required'],
            [['description'], 'string'],
            [['port', 'status', 'is_running', 'current_track_id', 'listeners_count'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name', 'folder_name', 'stream_url'], 'string', 'max' => 255],
            [['folder_name'], 'unique'],
            [['port'], 'unique'],
            [['stream_url'], 'url', 'defaultScheme' => 'http'],
            [['name', 'description', 'folder_name'], 'filter', 'filter' => '\yii\helpers\HtmlPurifier::process'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => Yii::t('common', 'Название радиостанции'),
            'description' => Yii::t('common', 'Описание'),
            'port' => Yii::t('common', 'Порт'),
            'folder_name' => Yii::t('common', 'Папка'),
            'stream_url' => Yii::t('common', 'URL потока'),
            'status' => Yii::t('common', 'Статус'),
            'is_running' => Yii::t('common', 'Запущена'),
            'current_track_id' => Yii::t('common', 'Текущий трек'),
            'listeners_count' => Yii::t('common', 'Слушателей'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'updated_at' => Yii::t('common', 'Обновлено'),
        ];
    }

    /**
     * Gets query for [[CurrentTrack]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCurrentTrack()
    {
        return $this->hasOne(RadioTrack::class, ['id' => 'current_track_id']);
    }

    /**
     * Gets query for [[RadioTracks]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRadioTracks()
    {
        return $this->hasMany(RadioTrack::class, ['radio_station_id' => 'id']);
    }

    /**
     * Gets approved tracks for this station
     *
     * @return \yii\db\ActiveQuery
     */
    public function getApprovedTracks()
    {
        return $this->hasMany(RadioTrack::class, ['radio_station_id' => 'id'])
            ->andWhere(['radio_track.status' => RadioTrack::STATUS_ACTIVE]);
    }

    /**
     * Get stream URL for frontend
     *
     * @return string
     */
    public function getStreamUrl()
    {
        // Если указан stream_url, используем его, иначе localhost
        if (!empty($this->stream_url)) {
            // Если в URL нет /stream, добавляем
            return rtrim($this->stream_url, '/') . '/stream';
        }
        return "http://localhost:{$this->port}/stream";
    }

    /**
     * Get folder path for tracks (использует uploads как единственный источник)
     *
     * @return string
     */
    public function getFolderPath()
    {
        $basePath = Yii::getAlias('@app');
        // Находим корень проекта (где находится frontend/)
        $rootPath = dirname($basePath);
        return $rootPath . '/frontend/web/uploads/radio/' . $this->id;
    }

    /**
     * Get web URL for uploaded tracks
     *
     * @return string
     */
    public function getUploadUrl()
    {
        return '/uploads/radio/' . $this->id;
    }

    /**
     * Get absolute web path for tracks (для Node.js)
     *
     * @return string
     */
    public function getAbsolutePath()
    {
        $basePath = Yii::getAlias('@app');
        $rootPath = dirname($basePath);
        return $rootPath . '/frontend/web/uploads/radio/' . $this->id;
    }
}

