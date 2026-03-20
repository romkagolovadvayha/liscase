<?php

use console\components\migration\Migration as BaseMigration;

/**
 * Ссылки-приглашения (Discord-like), заявки в клан, новости/страницы клана.
 */
class m260320_120000_clan_invite_links_applications_posts extends BaseMigration
{
    public function safeUp()
    {
        $this->createTable('clan_invite_links', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Клан\'',
            'token' => 'VARCHAR(64) NOT NULL COMMENT \'Уникальный токен ссылки\'',
            'inviter_user_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Кто создал\'',
            'expires_at' => 'DATETIME DEFAULT NULL COMMENT \'Истечение (NULL = без срока)\'',
            'max_uses' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'0 = без лимита\'',
            'uses_count' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-clan_invite_links-token', 'clan_invite_links', 'token', true);
        $this->createIndex('idx-clan_invite_links-clan_id', 'clan_invite_links', 'clan_id');

        $this->addForeignKey(
            'fk-clan_invite_links-clan',
            'clan_invite_links',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-clan_invite_links-user',
            'clan_invite_links',
            'inviter_user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->createTable('clan_applications', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL,
            'user_id' => self::INT_FIELD_NOT_NULL,
            'message' => 'TEXT NULL',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|accepted|rejected'",
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'resolved_at' => 'INT(10) UNSIGNED DEFAULT NULL',
            'resolved_by_user_id' => self::INT_FIELD,
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-clan_applications-clan', 'clan_applications', 'clan_id');
        $this->createIndex('idx-clan_applications-user', 'clan_applications', 'user_id');
        $this->createIndex('idx-clan_applications-status', 'clan_applications', 'status');

        $this->addForeignKey(
            'fk-clan_applications-clan',
            'clan_applications',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-clan_applications-user',
            'clan_applications',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->createTable('clan_posts', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL,
            'author_user_id' => self::INT_FIELD_NOT_NULL,
            'type' => "VARCHAR(20) NOT NULL DEFAULT 'news' COMMENT 'news|page'",
            'visibility' => "VARCHAR(20) NOT NULL DEFAULT 'public' COMMENT 'public|members|hidden'",
            'title' => 'VARCHAR(255) NOT NULL',
            'body' => 'MEDIUMTEXT NULL',
            'is_published' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1',
            'published_at' => 'INT(10) UNSIGNED NOT NULL',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-clan_posts-clan', 'clan_posts', 'clan_id');
        $this->createIndex('idx-clan_posts-published', 'clan_posts', ['clan_id', 'published_at']);

        $this->addForeignKey(
            'fk-clan_posts-clan',
            'clan_posts',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-clan_posts-author',
            'clan_posts',
            'author_user_id',
            'user',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-clan_posts-author', 'clan_posts');
        $this->dropForeignKey('fk-clan_posts-clan', 'clan_posts');
        $this->dropTable('clan_posts');

        $this->dropForeignKey('fk-clan_applications-user', 'clan_applications');
        $this->dropForeignKey('fk-clan_applications-clan', 'clan_applications');
        $this->dropTable('clan_applications');

        $this->dropForeignKey('fk-clan_invite_links-user', 'clan_invite_links');
        $this->dropForeignKey('fk-clan_invite_links-clan', 'clan_invite_links');
        $this->dropTable('clan_invite_links');
    }
}
