<?php

namespace frontend\forms\clans;

use common\models\clan\Clan;
use common\models\clan\UserClan;
use common\models\clan\UserRole;
use common\models\serverskin\ServerSkin;
use Imagine\Image\Point;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\validators\ImageValidator;
use yii\web\UploadedFile;

class LeaveForm extends Clan
{
    /**
     * @return bool
     */
    public function saveRecord()
    {
        $user = Yii::$app->user->identity;

        $userClans = UserClan::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['clan_id' => $this->id])
            ->exists();

        if (!$userClans) {
            $this->addError('global', Yii::t('common', 'Вы не состоите в этом клане'));
            return false;
        }

        /** @var UserClan[] $userClans */
        $userClans = UserClan::find()
                             ->andWhere(['clan_id' => $this->id])
                             ->all();

        if (count($userClans) == 1) {
            $this->delete();
            $this->id = null;
            return true;
        }

        $newLeader = null;
        foreach ($userClans as $userClan) {
            if ($userClan->user_id == $user->id) {
                $userClan->delete();
                continue;
            }
            if (empty($newLeader)) {
                $newLeader = $userClan->user;
            }
        }
        if ($this->user_id == $user->id) {
            $this->user_id = $newLeader->id;
            $this->save();
        }

        /** @var UserRole[] $userRoles */
        $userRoles = UserRole::find()
                             ->andWhere(['user_id' => $user->id])
                             ->andWhere(['clan_id' => $this->id])
                             ->all();
        foreach ($userRoles as $userRole) {
            $userRole->delete();
        }

        return true;
    }
}
