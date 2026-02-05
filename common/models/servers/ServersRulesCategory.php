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
            [['name', 'icon'], 'string', 'max' => 255],
            [['icon'], 'trim'],
            [['icon'], 'default', 'value' => null],
            [['sort'], 'default', 'value' => 0],
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
}

