<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * Новость или страница клана.
 *
 * @property int $id
 * @property int $clan_id
 * @property int $author_user_id
 * @property string $type
 * @property string $visibility
 * @property string $title
 * @property string|null $body
 * @property int $is_published
 * @property int $published_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Clan $clan
 * @property User $author
 */
class ClanPost extends ActiveRecord
{
    const TYPE_NEWS = 'news';
    const TYPE_PAGE = 'page';

    const VIS_PUBLIC = 'public';
    const VIS_MEMBERS = 'members';
    const VIS_HIDDEN = 'hidden';

    public static function tableName(): string
    {
        return 'clan_posts';
    }

    public function rules(): array
    {
        return [
            [['clan_id', 'author_user_id', 'title', 'published_at', 'created_at', 'updated_at'], 'required'],
            [['clan_id', 'author_user_id', 'is_published', 'published_at', 'created_at', 'updated_at'], 'integer'],
            [['body'], 'string'],
            [['title'], 'string', 'max' => 255],
            [['type'], 'in', 'range' => [self::TYPE_NEWS, self::TYPE_PAGE]],
            [['visibility'], 'in', 'range' => [self::VIS_PUBLIC, self::VIS_MEMBERS, self::VIS_HIDDEN]],
            [['type'], 'default', 'value' => self::TYPE_NEWS],
            [['visibility'], 'default', 'value' => self::VIS_PUBLIC],
            [['is_published'], 'default', 'value' => 1],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['author_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['author_user_id' => 'id']],
        ];
    }

    public function getClan(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    public function getAuthor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'author_user_id']);
    }
}
