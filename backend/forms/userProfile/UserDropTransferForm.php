<?php

namespace backend\forms\userProfile;

use common\models\user\User;
use common\models\user\UserDrop;
use Yii;
use yii\base\Model;

class UserDropTransferForm extends Model
{
    public $recipientSteamId;

    /** @var User|null */
    public $sender;

    /** @var User|null */
    private $_recipient;

    /** @var int|null */
    private $_transferableRowsCount;

    /** @var int */
    private $_transferredRowsCount = 0;

    /** @var int */
    private $_transferredItemsCount = 0;

    public function attributeLabels(): array
    {
        return [
            'recipientSteamId' => 'Steam ID получателя',
        ];
    }

    public function rules(): array
    {
        return [
            [['recipientSteamId', 'sender'], 'required'],
            [['recipientSteamId'], 'trim'],
            [['recipientSteamId'], 'match',
                'pattern' => '/^\d{8,20}$/',
                'message' => 'Укажите корректный Steam ID (от 8 до 20 цифр).',
            ],
            [['recipientSteamId'], 'validateRecipient'],
            [['recipientSteamId'], 'validateTransferableDrops'],
        ];
    }

    public function setUserId($userId): void
    {
        if (empty($userId)) {
            return;
        }

        $this->sender = User::findOne((int) $userId);
        if ($this->sender === null) {
            $this->addError('formError', 'Пользователь-отправитель не найден.');
        }
    }

    public function validateRecipient($attribute): void
    {
        if ($this->hasErrors() || $this->sender === null) {
            return;
        }

        $steamId = (string) $this->recipientSteamId;
        if ($steamId === (string) $this->sender->steam_id) {
            $this->addError($attribute, 'Получатель не может совпадать с отправителем.');
            return;
        }

        $this->_recipient = User::find()
            ->andWhere(['steam_id' => $steamId])
            ->one();

        if ($this->_recipient === null) {
            $this->addError($attribute, 'Пользователь с таким Steam ID не найден.');
        }
    }

    public function validateTransferableDrops($attribute): void
    {
        if ($this->hasErrors() || $this->sender === null) {
            return;
        }

        if ($this->getTransferableRowsCount() < 1) {
            $this->addError($attribute, 'У пользователя нет доступных предметов для переноса.');
        }
    }

    public function getTransferableRowsCount(): int
    {
        if ($this->_transferableRowsCount !== null) {
            return $this->_transferableRowsCount;
        }

        if ($this->sender === null) {
            return 0;
        }

        $count = UserDrop::find()
            ->andWhere([
                'user_id' => (int) $this->sender->id,
                'status' => UserDrop::STATUS_ACTIVE,
            ])
            ->count();

        $this->_transferableRowsCount = max(0, (int) $count);

        return $this->_transferableRowsCount;
    }

    public function getTransferredRowsCount(): int
    {
        return $this->_transferredRowsCount;
    }

    public function getTransferredItemsCount(): int
    {
        return $this->_transferredItemsCount;
    }

    public function saveRecord(): bool
    {
        if (!$this->validate() || $this->sender === null || $this->_recipient === null) {
            return false;
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $processingLockKeys = [];
        $processingLockValue = 'admin-transfer:' . Yii::$app->security->generateRandomString(16);

        try {
            $rows = $db->createCommand(
                'SELECT [[id]], COALESCE([[count]], 1) AS [[item_count]]
                 FROM {{%user_drop}}
                 WHERE [[user_id]] = :senderId AND [[status]] = :status
                 FOR UPDATE',
                [
                    ':senderId' => (int) $this->sender->id,
                    ':status' => UserDrop::STATUS_ACTIVE,
                ]
            )->queryAll();

            if (empty($rows)) {
                $this->_transferableRowsCount = 0;
                $transaction->rollBack();
                $this->addError('formError', 'Доступных предметов для переноса больше нет.');
                return false;
            }

            $ids = [];
            $itemsCount = 0;
            foreach ($rows as $row) {
                $ids[] = (int) $row['id'];
                $itemsCount += max(1, (int) $row['item_count']);
            }

            foreach ($ids as $id) {
                $lockKey = 'userDrop_lock_' . $id;
                if (!Yii::$app->cache->add($lockKey, $processingLockValue, 30)) {
                    throw new \DomainException(
                        'Один из предметов сейчас обрабатывается. Подождите несколько секунд и повторите перенос.'
                    );
                }
                $processingLockKeys[] = $lockKey;
            }

            $updatedRows = UserDrop::updateAll(
                ['user_id' => (int) $this->_recipient->id],
                [
                    'and',
                    ['id' => $ids],
                    ['user_id' => (int) $this->sender->id],
                    ['status' => UserDrop::STATUS_ACTIVE],
                ]
            );

            if ($updatedRows !== count($ids)) {
                throw new \RuntimeException('Не все предметы удалось заблокировать для переноса.');
            }

            $transaction->commit();

            $this->_transferredRowsCount = $updatedRows;
            $this->_transferredItemsCount = $itemsCount;
            $this->_transferableRowsCount = 0;

            $adminId = Yii::$app->user->id === null ? 0 : (int) Yii::$app->user->id;
            Yii::warning(sprintf(
                'UserDrop transfer by admin %d: sender %d (%s), recipient %d (%s), rows %d, items %d',
                $adminId,
                (int) $this->sender->id,
                (string) $this->sender->steam_id,
                (int) $this->_recipient->id,
                (string) $this->_recipient->steam_id,
                $this->_transferredRowsCount,
                $this->_transferredItemsCount
            ), __METHOD__);

            return true;
        } catch (\DomainException $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::warning('Перенос UserDrop отложен: ' . $e->getMessage(), __METHOD__);
            $this->addError('formError', $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error('Ошибка переноса UserDrop: ' . $e->getMessage(), __METHOD__);
            $this->addError('formError', 'Не удалось перенести предметы. Попробуйте ещё раз.');
            return false;
        } finally {
            foreach ($processingLockKeys as $lockKey) {
                if (Yii::$app->cache->get($lockKey) === $processingLockValue) {
                    Yii::$app->cache->delete($lockKey);
                }
            }
        }
    }
}
