<?php

namespace frontend\forms\clans;

use common\models\clan\Clan;
use common\models\clan\ClanQuestion;
use common\models\clan\UserClan;
use common\models\clan\UserRole;
use common\models\serverskin\ServerSkin;
use common\models\user\User;
use Imagine\Image\Point;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\validators\ImageValidator;
use yii\web\UploadedFile;

class QuestionForm extends ClanQuestion
{

    /**
     * @return bool
     */
    public function saveRecord()
    {
        if (!$this->validate()) {
            return false;
        }
        $user = Yii::$app->user->identity;

        $userClans = ClanQuestion::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['clan_id' => $this->clan_id])
            ->exists();

        if ($userClans) {
            $this->addError('global', Yii::t('common', 'Вы уже отправляли заявку в этот клан'));
            return false;
        }

        $this->status = ClanQuestion::STATUS_WAIT;
        $this->created_at = date('Y-m-d H:i:s');

        return $this->save();
    }
}
