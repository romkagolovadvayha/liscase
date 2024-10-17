<?php

namespace common\models\tasks;

use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserTask;
use Yii;

/**
 * This is the model class for table "task".
 *
 * @property int        $id
 * @property string     $description
 * @property int        $type
 * @property int        $amount
 * @property int        $drop_id_image
 * @property int        $drop_id
 * @property int        $count
 * @property string     $stat_attribute
 * @property int        $sort
 * @property string     $created_at
 *
 * @property UserTask[] $userTask
 * @property Drop       $drop
 * @property Drop       $dropImage
 */
class Task extends \common\components\base\ActiveRecord
{
    const TYPE_FERMER  = 1;
    const TYPE_REIDER  = 2;
    const TYPE_FARMER  = 3;
    const TYPE_FISHING = 4;
    const TYPE_KILLER  = 5;
    const TYPE_NPC     = 6;
    const TYPE_ANIMAL  = 7;


    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_FERMER    => Yii::t('common', 'Фермер'),
            self::TYPE_REIDER => Yii::t('common', 'Рейдер'),
            self::TYPE_FARMER => Yii::t('common', 'Фармер'),
            self::TYPE_FISHING => Yii::t('common', 'Рыбак'),
            self::TYPE_KILLER => Yii::t('common', 'Киллер'),
            self::TYPE_NPC => Yii::t('common', 'Мирный'),
            self::TYPE_ANIMAL => Yii::t('common', 'Охотник'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'task';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description', 'stat_attribute'], 'trim'],
            [['type', 'sort', 'drop_id_image', 'drop_id', 'count', 'amount'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'description'       => Yii::t('common', 'Описание'),
            'amount'       => Yii::t('common', 'Сколько нужно выполнить?'),
            'drop_id'       => Yii::t('common', 'ID предмета награды'),
            'count'       => Yii::t('common', 'Количество наград'),
            'drop_id_image'       => Yii::t('common', 'ID предмета для изображения'),
            'stat_attribute'       => Yii::t('common', 'Какой показатель статистики учитывать?'),
            'type' => Yii::t('common', 'Тип'),
            'created_at'  => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserTask()
    {
        return $this->hasMany(UserTask::class, ['task_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDrop()
    {
        return $this->hasOne(Drop::class, ['id' => 'drop_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDropImage()
    {
        return $this->hasOne(Drop::class, ['id' => 'drop_id_image']);
    }

    /**
     * @param User $user
     */
    public static function getTasksByUser($user, $type)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->andWhere('db_host IS NOT NULL')
                          ->all();

        /** @var Task[] $tasks */
        $tasks = Task::find()
                     ->cache(30)
                     ->andWhere(['type' => $type])
                     ->orderBy(['sort' => SORT_ASC])
                     ->all();

        /** @var UserTask[] $userTasks */
        $userTasks = $user->userTasks;

        $result = [];
        $available = false;
        $disabled = false;

        foreach ($tasks as $task) {
            $item = [
                'id' => $task->id,
                'taskImage' => $task->dropImage->imageOrig->getImagePubUrl(),
                'dropImage' => $task->drop->imageOrig->getImagePubUrl(),
                'drop_id' => $task->drop->id,
                'dropName' => $task->drop->name,
                'count' => $task->count,
                'description' => $task->description,
            ];
            foreach ($userTasks as $userTask) {
                if ($task->id === $userTask->task_id) {
                    $item['status'] = 0;
                    break;
                }
            }
            if (!isset($item['status']) && !$available && !$disabled) {
                $total = 0;
                foreach ($servers as $server) {
                    $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
                    $player = Statistics::find()
                                        ->cache(180)
                                        ->andWhere(['steam_id' => $user->steam_id])
                                        ->andWhere(['server_tag' => $server->tag])
                                        ->andWhere(['wipe' => $wipeDate])
                                        ->indexBy('key')
                                        ->all();
                    if (empty($player)) {
                        continue;
                    }
                    $total += Statistics::getParam($player, $task->stat_attribute);
                }
                if ($total >= $task->amount) {
                    $item['status'] = 1;
                    $available = true;
                }
            }
            if (!isset($item['status'])) {
                $item['status'] = 2;
                $disabled = true;
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param $type
     *
     * @return Task[]|\yii\db\ActiveRecord[]
     */
    public static function getTasksByType($type)
    {
        return Task::find()
                                   ->cache(3600)
                                   ->andWhere(['type' => $type])
                                   ->orderBy(['sort' => SORT_ASC])
                                   ->all();
    }

    /**
     * @param User $user
     *
     * @return array[]
     */
    public static function getDailyRewardList($user) {
        $dailyRewardList = [
            [
                'name' => Yii::t('common', '1 день'),
                'amount' => 10,
                'drop_id' => 570,
            ],
            [
                'name' => Yii::t('common', '2 день'),
                'amount' => 3000,
                'drop_id' => 295
            ],
            [
                'name' => Yii::t('common', '3 день'),
                'amount' => 3000,
                'drop_id' => 300
            ],
            [
                'name' => Yii::t('common', '4 день'),
                'amount' => 100,
                'drop_id' => 316
            ],
            [
                'name' => Yii::t('common', '5 день'),
                'amount' => 1,
                'drop_id' => 868
            ],
            [
                'name' => Yii::t('common', '6 день'),
                'amount' => 100,
                'drop_id' => 305
            ],
            [
                'name' => Yii::t('common', '7 день'),
                'amount' => 50,
                'type' => 'gift_small',
                'drop_id' => 843
            ],
            [
                'name' => Yii::t('common', '8 день'),
                'amount' => 2,
                'drop_id' => 203
            ],
            [
                'name' => Yii::t('common', '9 день'),
                'amount' => 2,
                'drop_id' => 626
            ],
            [
                'name' => Yii::t('common', '10 день'),
                'amount' => 1,
                'drop_id' => 869
            ],
            [
                'name' => Yii::t('common', '11 день'),
                'amount' => 1,
                'drop_id' => 867
            ],
            [
                'name' => Yii::t('common', '12 день'),
                'amount' => 1,
                'drop_id' => 864
            ],
            [
                'name' => Yii::t('common', '13 день'),
                'amount' => 1000,
                'drop_id' => 320
            ],
            [
                'name' => Yii::t('common', '14 день'),
                'amount' => 100.00,
                'type' => 'gift_big',
                'drop_id' => 843
            ],
        ];

        foreach ($dailyRewardList as $i => $dailyRewardItem) {
            if (!empty($dailyRewardItem['drop_id'])) {
                $drop = Drop::findOne($dailyRewardItem['drop_id']);
                $dailyRewardItem['drop_name'] = $drop->name;
                $dailyRewardItem['image'] = $drop->imageOrig->getImagePubUrl();
                $dailyRewardList[$i] = $dailyRewardItem;
            }
        }

        $date = new \DateTime();
        $date->modify('-29 day');

        $userBalance = $user->getPersonalBalance();

        $profits = Profit::find()
                         ->andWhere(['user_balance_id' => $userBalance->id])
                         ->andWhere(['IN', 'type', [Profit::TYPE_DAILY_REWARD_LIST, Profit::TYPE_DAILY_REWARD_LIST_BOX_SMALL, Profit::TYPE_DAILY_REWARD_LIST_BOX_BIG]])
                         ->andWhere(['>=', 'created_at', $date->format('Y-m-d 00:00:01')])
                         ->orderBy(['created_at' => SORT_DESC])
                         ->all();

        $received = 0;
        $date = new \DateTime();
        for ($i = 0; $i < count($profits) + 1; $i++) {
            $isReceived = false;
            foreach ($profits as $profit) {
                $createdAt = new \DateTime($profit->created_at);
                if ($createdAt->format('Y-m-d') === $date->format('Y-m-d')) {
                    if ($profit->type == Profit::TYPE_DAILY_REWARD_LIST_BOX_BIG) {
                        if ($createdAt->format('Y-m-d') === (new \DateTime())->format('Y-m-d')) {
                            $received++;
                        }
                        $isReceived = false;
                        break;
                    }
                    $isReceived = true;
                    $received++;
                    break;
                }
            }
            if (!$isReceived && $i !== 0) {
                break;
            }
            $date->modify('-1 day');
        }
        $exists = Profit::find()
                        ->andWhere(['user_balance_id' => $userBalance->id])
                        ->andWhere(['IN', 'type', [Profit::TYPE_DAILY_REWARD_LIST, Profit::TYPE_DAILY_REWARD_LIST_BOX_SMALL, Profit::TYPE_DAILY_REWARD_LIST_BOX_BIG]])
                        ->andWhere(['>=', 'created_at', date('Y-m-d 00:00:01')])
                        ->exists();
        if ($received > 0) {
            $received = $received % 14;
            if ($received === 0 && $exists) {
                $received = 14;
            }
        }

        for ($i = $received - 1; $i >= 0; $i--) {
            $dailyRewardList[$i]['status'] = 'completed';
        }

        if (!empty($dailyRewardList[$received]) && empty($dailyRewardList[$received]['status'])) {
            if (!$exists) {
                $dailyRewardList[$received]['status'] = 'available';
            }
        }

        return $dailyRewardList;
    }
}
