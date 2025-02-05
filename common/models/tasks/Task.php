<?php

namespace common\models\tasks;

use common\models\achievements\AchievementsDaily;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserTask;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "task".
 *
 * @property int        $id
 * @property string     $description
 * @property int        $type
 * @property int        $amount
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
    const TYPE_All  = 'all';


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
            self::TYPE_All => Yii::t('common', 'Все задания'),
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
            [['type', 'sort', 'drop_id', 'count', 'amount'], 'integer'],
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
     * @param User $user
     */
    public static function getTasksByUser($user)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        /** @var Task[] $tasks */
        $tasks = Task::find()
                     ->cache(30)
                     ->orderBy(['sort' => SORT_ASC])
                     ->all();

        /** @var UserTask[] $userTasks */
        $userTasks = $user->userTasks;

        $result = [];
        foreach ($tasks as $task) {
            if (empty($result[$task->type])) {
                $result[$task->type] = [];
                $result[$task->type]['disabled'] = false;
                $result[$task->type]['available'] = false;
                $result[$task->type]['type'] = $task->type;
                $result[$task->type]['completed'] = 0;
                $result[$task->type]['count'] = 0;
                $result[$task->type]['image'] = Task::awardImage($task->type);
                $result[$task->type]['name'] = ArrayHelper::getValue(Task::getTypeList(), $task->type);
                $result[$task->type]['items'] = [];
            }
            $completed = false;
            foreach ($userTasks as $userTask) {
                if ($task->id === $userTask->task_id) {
                    $completed = true;
                    break;
                }
            }
            if ($completed) {
                $result[$task->type]['completed']++;
                $result[$task->type]['count']++;
                $result[$task->type]['status'] = 'completed';
                $result[$task->type]['items'][] = [
                    'status' => 'completed',
                    'dropImage' => $task->drop->imageOrig->getImagePubUrl(),
                    'dropName' => $task->drop->name,
                    'count' => $task->count,
                ];
                continue;
            }
            $total = 0;
            if (!$result[$task->type]['available'] && !$result[$task->type]['disabled']) {
                foreach ($servers as $server) {
                    $player = Statistics::find()
                                        ->andWhere(['steam_id' => $user->steam_id])
                                        ->andWhere(['server_tag' => $server->tag])
                                        ->andWhere(['wipe' => $server->currentWipe()])
                                        ->indexBy('key')
                                        ->all();
                    if (empty($player)) {
                        continue;
                    }
                    $total += Statistics::getParam($player, $task->stat_attribute);
                }
                if ($total >= $task->amount) {
                    $result[$task->type]['count']++;
                    $result[$task->type]['completed']++;
                    $result[$task->type]['status'] = 'wait-get';
                    $result[$task->type]['info'] = [
                        'id' => $task->id,
                        'dropImage' => $task->drop->imageOrig->getImagePubUrl(),
                        'drop_id' => $task->drop->id,
                        'dropName' => $task->drop->name,
                        'count' => $task->count,
                        'amount' => $task->amount,
                        'description' => $task->description,
                        'stat_attribute' => $task->stat_attribute,
                        'total' => $total,
                    ];
                    $result[$task->type]['items'][] = [
                        'status' => 'wait-get',
                        'dropImage' => $task->drop->imageOrig->getImagePubUrl(),
                        'dropName' => $task->drop->name,
                        'count' => $task->count,
                    ];
                    $result[$task->type]['available'] = true;
                    continue;
                }
            }
            if (!isset($item['status'])) {
                if (empty($result[$task->type]['info'])) {
                    $result[$task->type]['info'] = [
                        'id' => $task->id,
                        'dropImage' => $task->drop->imageOrig->getImagePubUrl(),
                        'drop_id' => $task->drop->id,
                        'dropName' => $task->drop->name,
                        'count' => $task->count,
                        'amount' => $task->amount,
                        'description' => $task->description,
                        'stat_attribute' => $task->stat_attribute,
                        'total' => $total,
                    ];
                    $result[$task->type]['status'] = 'wait';
                }
                $result[$task->type]['count']++;
                $result[$task->type]['items'][] = [
                    'status' => 'wait',
                    'dropImage' => $task->drop->imageOrig->getImagePubUrl(),
                    'dropName' => $task->drop->name,
                    'count' => $task->count,
                ];
                $result[$task->type]['disabled'] = true;
            }
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
        $dailyRewardList = AchievementsDaily::find()
            ->cache(60)
            ->orderBy(['daily' => SORT_ASC])
            ->asArray()
            ->all();

        $dailyRewardList[count($dailyRewardList) - 1]['type'] = 'gift_big';

        $result = [];
        $result['received'] = false;
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
                $result['received'] = true;
                $dailyRewardList[$received]['status'] = 'available';
            }
        }

        foreach ($dailyRewardList as $i => $item) {
            if (empty($item['status'])) {
                $dailyRewardList[$i]['status'] = 'disabled';
            }
        }

        $result['items'] = $dailyRewardList;

        return $result;
    }

//    const TYPE_FERMER  = 1;
//    const TYPE_REIDER  = 2;
//    const TYPE_FARMER  = 3;
//    const TYPE_FISHING = 4;
//    const TYPE_KILLER  = 5;
//    const TYPE_NPC     = 6;
//    const TYPE_ANIMAL  = 7;
    public static function awardImage($type = 'all') {
        $images = [
          self::TYPE_FERMER => '/images/awards/fermer.png',
          self::TYPE_FARMER => '/images/awards/farmer.png',
          self::TYPE_FISHING => '/images/awards/fish.png',
          self::TYPE_REIDER => '/images/awards/reyder.png',
          self::TYPE_ANIMAL => '/images/awards/hunt.png',
          self::TYPE_KILLER => '/images/awards/killer.png',
          self::TYPE_NPC => '/images/awards/mirny.png',
          self::TYPE_All  => '/images/awards/all.png',
        ];

        return !empty($images[$type]) ? $images[$type] : '';
    }

    public static function awards($userId, $all = true) {
        $result = [];
        $awards = UserTask::find()
                        ->cache(60)
                        ->alias('ut')
                        ->joinWith('task t')
                        ->select(['t.type'])
                        ->andWhere(['IN', 't.drop_id', [843, 642, 859]])
                        ->andWhere(['ut.user_id' => $userId])
                        ->asArray()
                        ->groupBy('type')
                        ->indexBy('type')
                        ->all();

        foreach ($awards as $award) {
            $result[$award['type']] = [
                'image' => self::awardImage($award['type']),
                'name' => ArrayHelper::getValue(self::getTypeList(), $award['type']),
                'completed' => true
            ];
        }

        if ($all) {
            foreach (self::getTypeList() as $type => $name) {
                if (!empty($result[$type])) {
                    continue;
                }
                $result[$type] = [
                    'image'     => self::awardImage($type),
                    'name'      => $name,
                    'completed' => !empty($awards[$type])
                ];
            }
        }

        if (count($result) == 7) {
            $result[self::TYPE_All] = [
                'image' => self::awardImage(self::TYPE_All),
                'name' => ArrayHelper::getValue(self::getTypeList(), self::TYPE_All),
                'completed' => true
            ];
        }

        return $result;
    }

}
