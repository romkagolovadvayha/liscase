<?php

namespace frontend\forms\battle;

use common\models\battle\Battle;
use common\models\battle\BattleRate;
use common\models\user\UserDrop;
use Yii;
use yii\base\BaseObject;

class AddBattleForm extends Battle
{

    public $user_drop_id;

    public function rules(): array
    {
        return [
            [['user_drop_id'], 'required'],
            [['user_drop_id'], 'integer'],
        ];
    }

    /**
     * @return int
     */
    public function saveRecord(): int
    {
        if (!$this->validate()) {
            return false;
        }

        $battleId = null;
        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            $userId = Yii::$app->user->identity->id;
            $userDrop = UserDrop::findOne($this->user_drop_id);
            if ($userDrop->user->id !== $userId || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
                throw new \Exception(Yii::t('common', 'В вашем инвентаре не найден предмет!'));
            }
            $userDrop->status = UserDrop::STATUS_TEMP_BLOCKED;
            $userDrop->save(false);
            $battleId = Battle::createRecord($userId);
            BattleRate::createRecord($userId, $battleId, $userDrop->id);
            $dbTransaction->commit();
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            throw new \Exception(Yii::t('common', $e->getMessage()));
        }

        return $battleId;
    }

}
