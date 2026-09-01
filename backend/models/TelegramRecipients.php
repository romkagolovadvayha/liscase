<?php

namespace backend\models;

use common\helpers\HDates;
use common\helpers\HStrings;
use common\models\user\User;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "telegram_recipients".
 *
 * @property int $id
 * @property resource $ref_id
 * @property resource $name,
 * @property int $quantity
 * @property string|null $created_at
 */
class TelegramRecipients extends \yii\db\ActiveRecord
{
    private $_resolvedUserIds;
    private $_usageCount;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_recipients';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'ref_id'], 'required'],
            [['name'], 'trim'],
            [['name'], 'string', 'max' => 190],
            [['name'], 'unique'],
            [['quantity'], 'integer'],
            [['created_at', 'ref_id', 'quantity'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ref_id' => 'Код группы',
            'name' => 'Название группы',
            'quantity' => 'Количество участников',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * @inheritDoc
     */
    public function beforeSave($insert): bool
    {
        if ($insert) {
            $this->created_at = HDates::long();
        }
        return parent::beforeSave($insert);
    }

    /**
     * @throws \Exception
     */
    public function saveRecord(): bool
    {
        try {
            $this->_resolvedUserIds = null;
            $this->name = trim($this->name);
            $recipientValues = array_values(array_unique(array_filter((array)$this->ref_id, static function ($value) {
                return $value !== '' && $value !== null;
            })));
            $this->ref_id = $recipientValues;
            $this->quantity = count($recipientValues);
            if(array_key_exists($this->name, TelegramConstructor::getlkLanguagesArr())){
                $countUsers = User::find()->where(['current_language' => TelegramConstructor::getlkLanguagesArr()[$this->name], 'status' => User::STATUS_ACTIVE])->count();
                $this->quantity = $countUsers;
            }
            $this->ref_id = json_encode($recipientValues, JSON_UNESCAPED_UNICODE);
            if (!$this->save(false)) {
                return false;
            }
        } catch (\Exception $e) {
            if(self::find()->where(['name' => $this->name])->count()){
                $this->addError('name', 'Такое название уже существует');
            } else{
                \Yii::info("Telegram message not save " . print_r($e->getMessage(), 1), 'problem');
            }
            return false;
        }
        return true;
    }

    /**
     * @return array
     */
    public static function getList(): array
    {
        $data = self::find()->all();

        return ArrayHelper::map($data, 'id', 'name');
    }

    public function getRecipientValues(): array
    {
        $values = is_string($this->ref_id) ? json_decode($this->ref_id, true) : $this->ref_id;
        return is_array($values) ? array_values(array_filter($values, static function ($value) {
            return $value !== '' && $value !== null;
        })) : [];
    }

    public function getResolvedUserIds(): array
    {
        if ($this->_resolvedUserIds !== null) {
            return $this->_resolvedUserIds;
        }

        return $this->_resolvedUserIds = $this->getResolvedUserQuery()->column();
    }

    /**
     * Запрос участников сохранённой аудитории без промежуточной загрузки ID в PHP.
     */
    public function getResolvedUserQuery(): ActiveQuery
    {
        $query = User::find()
            ->select('id')
            ->andWhere(['status' => User::STATUS_ACTIVE]);

        $languageGroups = TelegramConstructor::getLkLanguagesArr();
        if ($this->name !== null && isset($languageGroups[(string)$this->name])) {
            return $query->andWhere(['current_language' => $languageGroups[(string)$this->name]]);
        }

        $values = $this->getRecipientValues();
        if (empty($values)) {
            return $query->andWhere('0=1');
        }

        return $query->andWhere(['or', ['id' => $values], ['ref_code' => $values]]);
    }

    public function getResolvedQuantity(): int
    {
        return (int)$this->getResolvedUserQuery()->count();
    }

    public function getUsageCount(): int
    {
        if ($this->_usageCount === null) {
            $this->_usageCount = (int)TelegramConstructor::find()
                ->andWhere(['audience_id' => TelegramConstructor::CUSTOM_AUDIENCE_OFFSET + (int)$this->id])
                ->count();
        }

        return $this->_usageCount;
    }

    public function getUsageLabel(): string
    {
        $count = $this->getUsageCount();
        return $count . ' ' . HStrings::pluralForm($count, ['рассылка', 'рассылки', 'рассылок']);
    }

    public function getSelectedUsersOptions(): array
    {
        $result = [];
        foreach ($this->getResolvedUserQuery()->select(['id', 'username'])->orderBy(['id' => SORT_DESC])->all() as $user) {
            $result[$user->id] = self::formatUserLabel($user);
        }
        return $result;
    }

    public static function formatUserLabel(User $user): string
    {
        $name = trim((string)$user->username);
        return $name !== '' ? sprintf('%s — ID %d', $name, $user->id) : sprintf('Пользователь ID %d', $user->id);
    }
}
