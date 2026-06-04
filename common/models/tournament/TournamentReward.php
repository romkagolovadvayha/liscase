<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $place
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $image
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Tournament $tournament
 */
class TournamentReward extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tournament_rewards';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tournament_id', 'place', 'title'], 'required'],
            [['tournament_id', 'place'], 'integer'],
            [['place'], 'in', 'range' => [1, 2, 3]],
            [['title', 'subtitle'], 'string', 'max' => 255],
            [['image'], 'string', 'max' => 512],
            [['tournament_id'], 'exist', 'targetClass' => Tournament::class, 'targetAttribute' => ['tournament_id' => 'id']],
        ];
    }

    public function getTournament()
    {
        return $this->hasOne(Tournament::class, ['id' => 'tournament_id']);
    }

    public function getImageUrl(): ?string
    {
        if (!$this->image) {
            return null;
        }
        if (strpos($this->image, 'http://') === 0 || strpos($this->image, 'https://') === 0) {
            return $this->image;
        }
        if (strpos($this->image, 'uploads/') === 0 && Yii::$app->has('s3Api')) {
            return Yii::$app->s3Api->getPublicUrl($this->image);
        }
        return $this->image;
    }
}
