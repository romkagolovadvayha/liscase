<?php

namespace backend\forms\tournament;

use common\models\tournament\Tournament;
use common\models\tournament\TournamentReward;
use Yii;
use yii\helpers\ArrayHelper;
use yii\imagine\Image;
use yii\web\UploadedFile;

/**
 * Форма турнира с обложкой и наградами 1–3 места.
 */
class TournamentForm extends Tournament
{
    /** @var UploadedFile|null */
    public $cover_file;

    /** @var string[] */
    public $reward_title = ['', '', ''];

    /** @var string[] */
    public $reward_subtitle = ['', '', ''];

    /** @var UploadedFile[] */
    public $reward_image_file = [];

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return ArrayHelper::merge(parent::rules(), [
            [['cover_file'], 'file', 'skipOnEmpty' => true, 'extensions' => ['png', 'jpg', 'jpeg', 'webp', 'gif'], 'maxSize' => 5 * 1024 * 1024],
            [['reward_title', 'reward_subtitle', 'reward_image_file'], 'safe'],
            [['reward_image_file'], 'each', 'rule' => [
                'file',
                'skipOnEmpty' => true,
                'extensions' => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
                'maxSize' => 5 * 1024 * 1024,
            ]],
        ]);
    }

    public function afterFind(): void
    {
        parent::afterFind();
        foreach (['starts_at', 'ends_at', 'registration_ends_at'] as $attr) {
            $val = $this->$attr;
            if (is_string($val) && $val !== '' && str_contains($val, ' ')) {
                $this->$attr = str_replace(' ', 'T', substr($val, 0, 16));
            }
        }
        $rewards = TournamentReward::find()
            ->where(['tournament_id' => (int)$this->id])
            ->indexBy('place')
            ->all();
        for ($place = 1; $place <= 3; $place++) {
            $idx = $place - 1;
            if (isset($rewards[$place])) {
                $this->reward_title[$idx] = (string)$rewards[$place]->title;
                $this->reward_subtitle[$idx] = (string)($rewards[$place]->subtitle ?? '');
            }
        }
    }

    public function saveWithUploads(): bool
    {
        foreach (['starts_at', 'ends_at', 'registration_ends_at'] as $attr) {
            $val = $this->$attr;
            if (is_string($val) && str_contains($val, 'T')) {
                $normalized = str_replace('T', ' ', $val);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
                    $normalized .= ':00';
                }
                $this->$attr = $normalized;
            }
        }
        if (is_string($this->tags) && $this->tags !== '') {
            $parts = array_map('trim', explode(',', $this->tags));
            $this->tags = json_encode(array_values(array_filter($parts)), JSON_UNESCAPED_UNICODE);
        }
        if (!$this->save()) {
            return false;
        }

        $cover = UploadedFile::getInstance($this, 'cover_file');
        if ($cover !== null) {
            $key = $this->uploadImageToS3($cover, 'cover');
            if ($key !== null) {
                $this->cover_image = $key;
                $this->save(false);
            }
        }

        $rewardFiles = UploadedFile::getInstances($this, 'reward_image_file');
        for ($place = 1; $place <= 3; $place++) {
            $idx = $place - 1;
            $title = trim((string)($this->reward_title[$idx] ?? ''));
            $subtitle = trim((string)($this->reward_subtitle[$idx] ?? ''));
            $file = $rewardFiles[$idx] ?? null;
            if ($title === '' && $subtitle === '' && $file === null) {
                continue;
            }
            if ($title === '') {
                $title = (string)$place . Yii::t('common', ' место');
            }
            $reward = TournamentReward::find()
                ->where(['tournament_id' => (int)$this->id, 'place' => $place])
                ->one();
            if (!$reward) {
                $reward = new TournamentReward();
                $reward->tournament_id = (int)$this->id;
                $reward->place = $place;
            }
            $reward->title = $title;
            $reward->subtitle = $subtitle !== '' ? $subtitle : null;
            if ($file !== null) {
                $key = $this->uploadImageToS3($file, 'reward' . $place);
                if ($key !== null) {
                    $reward->image = $key;
                }
            }
            $reward->save(false);
        }

        return true;
    }

    private function uploadImageToS3(UploadedFile $file, string $prefix): ?string
    {
        if (!Yii::$app->has('s3Api')) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'S3 не настроен'));
            return null;
        }
        $content = file_get_contents($file->tempName);
        if ($content === false) {
            return null;
        }
        try {
            $img = Image::getImagine()->load($content);
            $size = $img->getSize();
            if ($size->getWidth() > 1920 || $size->getHeight() > 1080) {
                $img = Image::thumbnail($img, 1920, 1080);
            }
            $pngData = $img->get('png');
        } catch (\Throwable $e) {
            $ext = strtolower($file->extension ?: 'png');
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $pngData = $content;
            $key = 'uploads/tournaments/' . $prefix . '_' . (int)$this->id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            return Yii::$app->s3Api->putFile($key, $pngData, $mime) !== false ? $key : null;
        }
        $key = 'uploads/tournaments/' . $prefix . '_' . (int)$this->id . '_' . bin2hex(random_bytes(6)) . '.png';
        return Yii::$app->s3Api->putFile($key, $pngData, 'image/png') !== false ? $key : null;
    }
}
