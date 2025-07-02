<?php

namespace common\models\tasks;

use common\components\tasks\TaskComponent;
use common\models\user\User;
use common\models\user\UserTasks;
use Yii;

/**
 * This is the model class for table "tasks".
 *
 * @property int $id
 * @property string|null $image
 * @property string|null $name
 * @property string|null $short_name
 * @property int|null $tasks_publish_place_id
 * @property int|null $tasks_projects_id
 * @property string|null $date_start
 * @property string|null $date_end
 * @property string|null $description
 * @property int|null $amount
 * @property string|null $amount_icon
 * @property string|null $additional_text
 * @property string|null $url_text
 * @property string|null $url_link
 * @property string|null $button_text
 * @property string|null $button_url
 * @property string|null $reward_amount_signature
 * @property string|null $additional_explanation
 * @property string|null $additional_url_text
 * @property string|null $additional_url_link
 * @property int $is_email_field
 * @property int $is_check_method_auto
 * @property int $is_permanent
 * @property int $is_publish
 * @property int $is_archive
 * @property int|null $order_index
 * @property string|null $system_check_code
 * @property string|null $created_at
 * @property int $promotion_id
 * @property string|null $lk_lang
 * @property string|null $video_link
 * @property string|null $stat_param
 * @property int $stat_count
 *
 * @property TasksProjects $tasksProjects
 * @property TasksPublishPlace $tasksPublishPlace
 * @property TasksTagsAppointments[] $tasksTagsAppointments
 */
class Tasks extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tasks_publish_place_id', 'tasks_projects_id', 'amount', 'is_email_field', 'is_check_method_auto', 'is_permanent', 'is_publish', 'order_index'], 'integer'],
            [['date_start', 'date_end', 'created_at', 'lk_lang', 'video_link'], 'safe'],
            [['image', 'name', 'short_name', 'description', 'amount_icon', 'additional_text', 'url_text', 'url_link', 'button_text', 'button_url', 'reward_amount_signature', 'additional_explanation', 'additional_url_text', 'additional_url_link', 'system_check_code', 'lk_lang'], 'string', 'max' => 255],
            [['tasks_projects_id'], 'exist', 'skipOnError' => true, 'targetClass' => TasksProjects::className(), 'targetAttribute' => ['tasks_projects_id' => 'id']],
            [['tasks_publish_place_id'], 'exist', 'skipOnError' => true, 'targetClass' => TasksPublishPlace::className(), 'targetAttribute' => ['tasks_publish_place_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'image' => 'Image',
            'name' => 'Name',
            'short_name' => 'Short Name',
            'tasks_publish_place_id' => 'Tasks Publish Place ID',
            'tasks_projects_id' => 'Tasks Projects ID',
            'date_start' => 'Date Start',
            'date_end' => 'Date End',
            'description' => 'Description',
            'amount' => 'Amount',
            'amount_icon' => 'Amount Icon',
            'additional_text' => 'Additional Text',
            'url_text' => 'Url Text',
            'url_link' => 'Url Link',
            'button_text' => 'Button Text',
            'button_url' => 'Button Url',
            'reward_amount_signature' => 'Reward Amount Signature',
            'additional_explanation' => 'Additional Explanation',
            'additional_url_text' => 'Additional Url Text',
            'additional_url_link' => 'Additional Url Link',
            'is_email_field' => 'Is Email Field',
            'is_check_method_auto' => 'Is Check Method Auto',
            'is_permanent' => 'Is Permanent',
            'is_publish' => 'Is Publish',
            'order_index' => 'Order Index',
            'system_check_code' => 'System Check Code',
            'created_at' => 'Created At',
            'lk_lang' => 'LK Lang',
            'video_link' => 'Video Link'
        ];
    }

    /**
     * Gets query for [[TasksProjects]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTasksProjects()
    {
        return $this->hasOne(TasksProjects::className(), ['id' => 'tasks_projects_id']);
    }

    /**
     * Gets query for [[TasksPublishPlace]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTasksPublishPlace()
    {
        return $this->hasOne(TasksPublishPlace::className(), ['id' => 'tasks_publish_place_id']);
    }

    /**
     * Gets query for [[TasksTagsAppointments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTasksTagsAppointments()
    {
        return $this->hasMany(TasksTagsAppointments::className(), ['task_id' => 'id']);
    }

    /**
     * @param $publishPlaceId
     * @param $projectId
     * @param $userTasks
     * @param $statuses
     * @param User $user
     * @param $showAll
     *
     * @return bool
     */
    public function hasVisibility($publishPlaceId, $projectId, $userTasks, $statuses, $user, $showAll, Tasks $tasks=null): bool
    {
        $component = TaskComponent::getInstance($this->system_check_code);
        if (($showAll && !$component->visability($user, $this))) {
            if (empty($userTasks[$this->id]) || $userTasks[$this->id]->status !== UserTasks::STATUS_GET_PROFIT) {
                return false;
            }
        }

        if($tasks !== null && !empty($tasks->lk_lang)){
            $lkLangArr = json_decode($tasks->lk_lang,1);
            if(!in_array($user->current_language, $lkLangArr)){
                return false;
            }
        }

        if (!$showAll && empty($userTasks[$this->id])) {
            return false;
        }
        if (!empty($userTasks[$this->id]) && !in_array($userTasks[$this->id]->status, $statuses)) {
            if (!$this->is_permanent) {
                return false;
            }
        }
        if ($projectId != $this->tasks_projects_id || $publishPlaceId != $this->tasks_publish_place_id) {
            return false;
        }
        return true;
    }

    public static function translateLinks($link = null, $language = null) {

        $decodedLink = json_decode($link,1);
        if(!empty($decodedLink) && isset($decodedLink[$language])){
            return $decodedLink[$language];
        } elseIf (!empty($decodedLink) && !isset($decodedLink[$language]) && isset($decodedLink["en-US"])) {
            return $decodedLink["en-US"];
        } elseIf (!empty($decodedLink) && !isset($decodedLink[$language]) && !isset($decodedLink["en-US"]) && is_array($decodedLink)) {
            return '';
        }

        $list = [
            'https://t.me/+unWA6W3RkjRjYTYy' => [
                'ru-RU' => 'https://t.me/+KnC8AT29xTs4MDAy',
            ],
            'https://t.me/eywa_channel' => [
                'ru-RU' => 'https://t.me/eywa_ru_channel',
            ],
            'https://t.me/eywa_en' => [
                'fr-FR' => 'https://t.me/eywa_fr',
                'vi-VN' => 'https://t.me/eywa_vn',
                'id-ID' => 'https://t.me/eywa_idn',
                'hi-IN' => 'https://t.me/eywa_in',
                'tr-TR' => 'https://t.me/eywa_tr',
                'hr-HR' => 'https://t.me/eywa_asia',
                'ru-RU' => 'https://t.me/eywa_ru',
            ],
            'https://t.me/webwisepad_channel' => [
                'ru-RU' => 'https://t.me/WebwisePad_channel_CIS',
            ],
            'https://t.me/webwisepad_chat' => [
                'ru-RU' => 'https://t.me/webwisepad_cis',
            ],
            'https://t.me/Antipad_chat' => [
                'ru-RU' => 'https://t.me/Antipad_chat_ru',
            ],
        ];

        if (!empty($link)) {
            if (!empty($list[$link]) && !empty($list[$link][$language])) {
                return $list[$link][$language];
            }
            return $link;
        }

        return $list;
    }

    /**
     * @return string[]
     */
    public function getLkLangArr(): array
    {
        return [
            'ru-RU' => 'Ru',
            'en-US' => 'En',
            'de-DE' => 'De',
            'it-IT' => 'It',
            'es-ES' => 'Es',
            'fr-FR' => 'Fr',
            'vi-VN' => 'Vn',
            'id-ID' => 'Id',
            'hi-IN' => 'Hi',
            'pt-PT' => 'Pt',
            'tr-TR' => 'Tr',
            'hr-HR' => 'Hr',
        ];
    }
}
