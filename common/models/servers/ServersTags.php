<?php

namespace common\models\servers;

use Yii;

/**
 * This is the model class for table "servers_tags".
 *
 * @property int $id
 * @property string $name Название тега
 * @property string|null $title Заголовок (title)
 * @property string $link_name Название для ссылки
 * @property string|null $short_description Краткое описание
 * @property string|null $description Полное описание
 * @property string|null $color Цвет тега (HEX)
 * @property string|null $icon Иконка тега
 * @property int|null $sort Сортировка
 * @property int|null $status Статус (0-неактивен, 1-активен)
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ServersTagsRelation[] $serversTagsRelations
 * @property Servers[] $servers
 */
class ServersTags extends \common\components\base\ActiveRecord
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers_tags';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'link_name'], 'required'],
            [['description'], 'string'],
            [['sort', 'status'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name', 'title', 'link_name', 'icon'], 'string', 'max' => 255],
            [['icon'], 'trim'],
            [['icon'], 'default', 'value' => null],
            [['short_description'], 'string', 'max' => 500],
            [['color'], 'string', 'max' => 7],
            [['color'], 'match', 'pattern' => '/^#[0-9A-Fa-f]{6}$/', 'skipOnEmpty' => true],
            [['link_name'], 'unique'],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['sort'], 'default', 'value' => 0],
            [['color'], 'default', 'value' => '#3498db'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'name' => Yii::t('common', 'Название тега'),
            'title' => Yii::t('common', 'Заголовок (title)'),
            'link_name' => Yii::t('common', 'Название для ссылки'),
            'short_description' => Yii::t('common', 'Краткое описание'),
            'description' => Yii::t('common', 'Полное описание'),
            'color' => Yii::t('common', 'Цвет тега'),
            'icon' => Yii::t('common', 'Иконка тега'),
            'sort' => Yii::t('common', 'Сортировка'),
            'status' => Yii::t('common', 'Статус'),
            'created_at' => Yii::t('common', 'Создан'),
            'updated_at' => Yii::t('common', 'Обновлен'),
        ];
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_INACTIVE => Yii::t('common', 'Неактивен'),
            self::STATUS_ACTIVE => Yii::t('common', 'Активен'),
        ];
    }

    /**
     * Gets query for [[ServersTagsRelations]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServersTagsRelations()
    {
        return $this->hasMany(ServersTagsRelation::class, ['tag_id' => 'id']);
    }

    /**
     * Gets query for [[Servers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServers()
    {
        return $this->hasMany(Servers::class, ['id' => 'server_id'])
            ->viaTable('servers_tags_relation', ['tag_id' => 'id']);
    }

    /**
     * @return string
     */
    public function getStatusName()
    {
        $list = self::getStatusList();
        return $list[$this->status] ?? '';
    }

    /**
     * Получить список тегов для dropdown
     * @return array
     */
    public static function getTagsList()
    {
        return self::find()
            ->select(['name', 'id'])
            ->where(['status' => self::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }


    public function getLink($key = null)
    {
        return "/servers/tag-{$this->link_name}";
    }

    /**
     * Сохранение записи (для совместимости с CrudController)
     * @return bool
     */
    public function saveRecord()
    {
        // Отладка для icon
        Yii::info('ServersTags::saveRecord - Icon value before save: ' . ($this->icon ?? 'NULL'), __METHOD__);
        $result = $this->save();
        if (!$result) {
            print_r(print_r($this->getErrors(), true));exit;
            Yii::error('ServersTags::saveRecord - Save failed. Errors: ' . print_r($this->getErrors(), true), __METHOD__);
        } else {
            // Сбрасываем кэш после сохранения
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Сброс кэша после сохранения/удаления
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $this->clearCache();
    }

    /**
     * Сброс кэша после удаления
     */
    public function afterDelete()
    {
        parent::afterDelete();
        $this->clearCache();
    }

    /**
     * Очистка связанных кэшей
     */
    protected function clearCache()
    {
        // Сбрасываем кэш списка серверов (теги используются в форматировании)
        Yii::$app->cache->delete('api_servers_index');
        
        // Сбрасываем кэш для конкретного тега, если есть link
        if ($this->link_name) {
            Yii::$app->cache->delete('api_servers_tag_' . $this->link_name);
        }
    }
}

