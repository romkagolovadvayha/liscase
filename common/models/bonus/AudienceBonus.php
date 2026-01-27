<?php

namespace common\models\bonus;

use Yii;
use common\models\user\User;

/**
 * This is the model class for table "audience_bonus".
 *
 * @property int $id
 * @property int $audience_type Тип аудитории: 1 - депозиты, 2 - вайпы
 * @property string|null $parameters_json JSON с параметрами начисления
 * @property string|null $message_template Шаблон сообщения для ТГ бота
 * @property string|null $test_user_ids JSON массив ID пользователей для тестирования
 * @property int $total_users Общее количество пользователей
 * @property float $total_amount Общая сумма начисления
 * @property string|null $created_at
 * @property int|null $created_by ID пользователя, создавшего начисление
 *
 * @property User $createdBy
 */
class AudienceBonus extends \common\components\base\ActiveRecord
{
    const AUDIENCE_TYPE_DEPOSITS = 1;
    const AUDIENCE_TYPE_WIPES = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'audience_bonus';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['audience_type', 'total_users', 'total_amount'], 'required'],
            [['audience_type', 'total_users', 'created_by'], 'integer'],
            [['parameters_json', 'message_template', 'test_user_ids'], 'string'],
            [['total_amount'], 'number'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'audience_type' => 'Тип аудитории',
            'parameters_json' => 'Параметры',
            'message_template' => 'Шаблон сообщения',
            'test_user_ids' => 'Тестовая аудитория',
            'total_users' => 'Количество пользователей',
            'total_amount' => 'Общая сумма',
            'created_at' => 'Дата создания',
            'created_by' => 'Создал',
        ];
    }

    /**
     * Получить список типов аудитории
     * @return array
     */
    public static function getAudienceTypeList()
    {
        return [
            self::AUDIENCE_TYPE_DEPOSITS => 'Депозиты',
            self::AUDIENCE_TYPE_WIPES => 'Вайпы',
        ];
    }

    /**
     * Получить название типа аудитории
     * @return string
     */
    public function getAudienceTypeName()
    {
        $list = self::getAudienceTypeList();
        return $list[$this->audience_type] ?? 'Неизвестно';
    }

    /**
     * Получить параметры в виде массива
     * @return array
     */
    public function getParameters()
    {
        if (empty($this->parameters_json)) {
            return [];
        }
        return json_decode($this->parameters_json, true) ?: [];
    }

    /**
     * Установить параметры из массива
     * @param array $params
     */
    public function setParameters(array $params)
    {
        $this->parameters_json = json_encode($params);
    }

    /**
     * Получить тестовые ID пользователей в виде массива
     * @return array|null
     */
    public function getTestUserIds()
    {
        if (empty($this->test_user_ids)) {
            return null;
        }
        return json_decode($this->test_user_ids, true);
    }

    /**
     * Установить тестовые ID пользователей из массива
     * @param array|null $userIds
     */
    public function setTestUserIds($userIds)
    {
        if (empty($userIds)) {
            $this->test_user_ids = null;
        } else {
            $this->test_user_ids = json_encode($userIds);
        }
    }

    /**
     * Является ли начисление тестовым
     * @return bool
     */
    public function isTest()
    {
        return !empty($this->test_user_ids);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
}

