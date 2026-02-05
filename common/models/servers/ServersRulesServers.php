<?php

namespace common\models\servers;

use Yii;

/**
 * This is the model class for table "servers_rules_servers".
 *
 * @property int $id
 * @property int $rule_id ID правила
 * @property int $server_id ID сервера
 * @property int $created_at
 *
 * @property ServersRules $rule
 * @property Servers $server
 */
class ServersRulesServers extends \common\components\base\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers_rules_servers';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['rule_id', 'server_id'], 'required'],
            [['rule_id', 'server_id', 'created_at'], 'integer'],
            [['rule_id', 'server_id'], 'unique', 'targetAttribute' => ['rule_id', 'server_id']],
            [['rule_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServersRules::class, 'targetAttribute' => ['rule_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'rule_id' => Yii::t('common', 'ID правила'),
            'server_id' => Yii::t('common', 'ID сервера'),
            'created_at' => Yii::t('common', 'Создан'),
        ];
    }

    /**
     * Gets query for [[Rule]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRule()
    {
        return $this->hasOne(ServersRules::class, ['id' => 'rule_id']);
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
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
                'updatedAtAttribute' => false,
            ],
        ];
    }
}

