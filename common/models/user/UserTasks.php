<?php

namespace common\models\user;

use common\models\lottery\BigGame;
use common\models\tasks\Tasks;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "user_tasks".
 *
 * @property int         $id
 * @property int         $task_id
 * @property int         $user_id
 * @property int         $status
 * @property string      $result
 * @property float       $amount
 * @property int         $awarded
 * @property string|null $finished_at
 * @property string|null $created_at
 *
 * @property Tasks $task
 * @property User $user
 */
class UserTasks extends \yii\db\ActiveRecord
{
    const STATUS_CREATED  = 0;
    const STATUS_WAITING  = 1;
    const STATUS_SUCCESS  = 2;
    const STATUS_REJECTED = 3;
    const STATUS_GET_PROFIT = 6;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_tasks';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_WAITING    => Yii::t('common', 'На проверке'),
            self::STATUS_SUCCESS    => Yii::t('common', 'Выполнено'),
            self::STATUS_REJECTED   => Yii::t('common', 'Отклонено'),
            self::STATUS_CREATED => Yii::t('common', 'Зарегистрировано'),
            self::STATUS_GET_PROFIT => Yii::t('common', 'Забрать награду'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['task_id', 'user_id'], 'required'],
            [['task_id', 'user_id', 'status', 'awarded'], 'integer'],
            [['finished_at', 'created_at'], 'safe'],
            [['task_id'], 'exist', 'skipOnError' => true, 'targetClass' => Tasks::className(), 'targetAttribute' => ['task_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'task_id' => 'Task ID',
            'user_id' => 'User ID',
            'status' => 'Status',
            'awarded' => 'Awarded',
            'finished_at' => 'Finished At',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Task]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTask()
    {
        return $this->hasOne(Tasks::className(), ['id' => 'task_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public static function successTaskBeginner() {
        $user = Yii::$app->user->identity;
        $bigGameModel = BigGame::findOne(['user_id' => $user->id]);
        if(empty($bigGameModel)){
            $bigGameModel = new BigGame();
            $bigGameModel->user_id = (int) $user->id;
            $bigGameModel->save();
            Yii::$app->session->addFlash('success', Yii::t('common', 'Заявка на участие успешно зарегистрирована!'));
        }
        $bigGameDigiuInfo = Tasks::find()
            ->andWhere(['system_check_code' => 'big_game_digiu_info'])
            ->one();

        if (empty($bigGameDigiuInfo)) {
            return false;
        }

        $userTasks = UserTasks::find()
            ->andWhere(['task_id' => $bigGameDigiuInfo->id])
            ->andWhere(['user_id' => $user->id])
            ->one();

        if (!empty($userTasks) && in_array($userTasks->status, [UserTasks::STATUS_GET_PROFIT, UserTasks::STATUS_SUCCESS])) {
            return true;
        }

        if (empty($userTasks)) {
            $userTasks = new UserTasks();
            $userTasks->user_id = $user->id;
            $userTasks->task_id = $bigGameDigiuInfo->id;
            $userTasks->created_at = date('Y-m-d H:i:s');
        }
        $userTasks->finished_at = date('Y-m-d H:i:s');
        $userTasks->status = UserTasks::STATUS_GET_PROFIT;
        $userTasks->save();
        return true;
    }
}
