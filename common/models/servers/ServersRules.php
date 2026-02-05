<?php

namespace common\models\servers;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "servers_rules".
 *
 * @property int $id
 * @property int $category_id ID категории
 * @property string|null $title Название правила (опционально)
 * @property string $content Содержание правила (HTML)
 * @property string|null $punishment Наказание за нарушение
 * @property int $sort Порядок сортировки
 * @property int $created_at
 * @property int $updated_at
 *
 * @property ServersRulesCategory $category
 * @property ServersRulesServers[] $serversRulesServers
 * @property Servers[] $servers
 */
class ServersRules extends \common\components\base\ActiveRecord
{
    /**
     * @var array Виртуальное свойство для хранения выбранных серверов
     */
    public $serverIds = [];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers_rules';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['category_id', 'content'], 'required'],
            [['category_id', 'sort', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['title', 'punishment'], 'string', 'max' => 500],
            [['sort'], 'default', 'value' => 0],
            [['serverIds'], 'safe'],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServersRulesCategory::class, 'targetAttribute' => ['category_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'category_id' => Yii::t('common', 'Категория'),
            'serverIds' => Yii::t('common', 'Серверы'),
            'title' => Yii::t('common', 'Название правила'),
            'content' => Yii::t('common', 'Содержание правила (HTML)'),
            'punishment' => Yii::t('common', 'Наказание за нарушение'),
            'sort' => Yii::t('common', 'Порядок сортировки'),
            'created_at' => Yii::t('common', 'Создан'),
            'updated_at' => Yii::t('common', 'Обновлен'),
        ];
    }

    /**
     * Gets query for [[Category]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(ServersRulesCategory::class, ['id' => 'category_id']);
    }

    /**
     * Gets query for [[ServersRulesServers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServersRulesServers()
    {
        return $this->hasMany(ServersRulesServers::class, ['rule_id' => 'id']);
    }

    /**
     * Gets query for [[Servers]] через связующую таблицу.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServers()
    {
        return $this->hasMany(Servers::class, ['id' => 'server_id'])
            ->viaTable('servers_rules_servers', ['rule_id' => 'id']);
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
     * После загрузки модели заполняем serverIds
     */
    public function afterFind()
    {
        parent::afterFind();
        // Загружаем серверы через связь
        $this->serverIds = ArrayHelper::getColumn($this->getServers()->all(), 'id');
    }

    /**
     * Сохранение связей с серверами
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        // Удаляем старые связи
        ServersRulesServers::deleteAll(['rule_id' => $this->id]);
        
        // Создаем новые связи
        if (!empty($this->serverIds) && is_array($this->serverIds)) {
            foreach ($this->serverIds as $serverId) {
                $relation = new ServersRulesServers();
                $relation->rule_id = $this->id;
                $relation->server_id = $serverId;
                $relation->save();
            }
        }
    }

    /**
     * Получить правила для сервера (общие + специфичные для сервера)
     * @param int|null $serverId ID сервера
     * @return array
     */
    public static function getRulesForServer($serverId = null)
    {
        $query = self::find()
            ->with(['category'])
            ->distinct();

        if ($serverId === null) {
            // Если сервер не указан, возвращаем только общие правила
            // Общие правила - это те, у которых нет записей в связующей таблице
            $query->where([
                'not in',
                'id',
                ServersRulesServers::find()->select('rule_id')
            ]);
        } else {
            // Получаем правила, которые либо общие, либо привязаны к этому серверу
            $query->where([
                'or',
                // Общие правила (нет связей в связующей таблице)
                ['not in', 'id', ServersRulesServers::find()->select('rule_id')],
                // Правила для конкретного сервера через связующую таблицу
                ['in', 'id', ServersRulesServers::find()->select('rule_id')->where(['server_id' => $serverId])]
            ]);
        }

        $query->orderBy(['category_id' => SORT_ASC, 'sort' => SORT_ASC]);

        return $query->all();
    }
}

