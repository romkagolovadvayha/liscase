<?php

namespace backend\models;

use common\helpers\HDates;
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
            [['name'], 'required'],
            [['name'], 'string'],
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
            'ref_id' => 'Ref Code',
            'name' => 'Название группы',
            'quantity' => 'Количество участников',
            'created_at' => 'Created At',
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
            $this->name = trim($this->name);
            $this->quantity = count((array)$this->ref_id);
            if(array_key_exists($this->name, TelegramConstructor::getlkLanguagesArr())){
                $countUsers = User::find()->where(['current_language' => TelegramConstructor::getlkLanguagesArr()[$this->name], 'status' => User::STATUS_ACTIVE])->count();
                $this->quantity = $countUsers;
            }
            $this->ref_id = json_encode($this->ref_id);
            $this->save(false);
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
}
