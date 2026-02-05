<?php

namespace common\models\servers;

use Yii;

/**
 * This is the model class for table "servers_rules_categories".
 *
 * @property int $id
 * @property string $name Название категории
 * @property string|null $icon Иконка категории
 * @property int $sort Порядок сортировки
 * @property bool $no_numbering Отключить нумерацию правил в категории
 * @property int $created_at
 * @property int $updated_at
 *
 * @property ServersRules[] $serversRules
 */
class ServersRulesCategory extends \common\components\base\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers_rules_categories';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['sort', 'created_at', 'updated_at'], 'integer'],
            [['no_numbering'], 'boolean'],
            [['name', 'icon'], 'string', 'max' => 255],
            [['icon'], 'trim'],
            [['icon'], 'default', 'value' => null],
            [['sort'], 'default', 'value' => 0],
            [['no_numbering'], 'default', 'value' => 0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'name' => Yii::t('common', 'Название категории'),
            'icon' => Yii::t('common', 'Иконка категории'),
            'sort' => Yii::t('common', 'Порядок сортировки'),
            'no_numbering' => Yii::t('common', 'Отключить нумерацию правил'),
            'created_at' => Yii::t('common', 'Создан'),
            'updated_at' => Yii::t('common', 'Обновлен'),
        ];
    }

    /**
     * Gets query for [[ServersRules]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServersRules()
    {
        return $this->hasMany(ServersRules::class, ['category_id' => 'id'])
            ->orderBy(['sort' => SORT_ASC]);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => \yii\behaviors\TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    /**
     * Сброс кэша после сохранения
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $this->clearRulesCache();
    }

    /**
     * Сброс кэша после удаления
     */
    public function afterDelete()
    {
        parent::afterDelete();
        $this->clearRulesCache();
    }

    /**
     * Очистка кэша правил для всех серверов
     */
    protected function clearRulesCache()
    {
        // Получаем все серверы
        $allServers = \common\models\servers\Servers::find()
            ->select('tag')
            ->column();
        
        // Сбрасываем кэш для всех серверов
        foreach ($allServers as $serverTag) {
            Yii::$app->cache->delete('api_servers_rules_' . $serverTag);
        }
    }
}

