<?php

use console\components\migration\Migration;
use yii\db\Schema;
use yii\db\ColumnSchema;

/**
 * Handles the creation of tables for clan system
 */
class m260127_103915_create_clan_system_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Удаляем таблицу, если она существует (для случаев частичного применения миграции)
        $tableSchema = $this->db->schema->getTableSchema('clans');
        if ($tableSchema !== null) {
            $this->dropTable('clans');
        }
        
        // Определяем типы данных для внешних ключей
        $serversIdColumn = $this->db->schema->getTableSchema('{{%servers}}')->getColumn('id');
        $userIdColumn = $this->db->schema->getTableSchema('{{%user}}')->getColumn('id');
        
        $serverIdType = $this->resolveColumnType($serversIdColumn);
        $userIdType = $this->resolveColumnType($userIdColumn);
        
        // Таблица кланов
        $this->createTable('clans', [
            'id' => self::PRIMARY_KEY,
            'name' => 'VARCHAR(255) NOT NULL COMMENT \'Название клана\'',
            'tag' => 'VARCHAR(50) NOT NULL COMMENT \'Тег клана\'',
            'leader_user_id' => $userIdType->notNull()->comment('ID лидера'),
            'server_id' => $serverIdType->notNull()->comment('ID сервера'),
            'motto' => 'TEXT DEFAULT NULL COMMENT \'Девиз клана\'',
            'logo' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Путь к логотипу клана\'',
            'privacy' => "ENUM('open', 'closed', 'invite_only') DEFAULT 'invite_only' COMMENT 'Тип приватности'",
            'description' => 'TEXT DEFAULT NULL COMMENT \'Описание клана\'',
            'level' => 'INT(10) UNSIGNED DEFAULT 1 COMMENT \'Уровень клана\'',
            'experience' => 'INT(10) UNSIGNED DEFAULT 0 COMMENT \'Опыт клана\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clans-leader_user_id', 'clans', 'leader_user_id');
        $this->createIndex('idx-clans-server_id', 'clans', 'server_id');
        $this->createIndex('idx-clans-name-server', 'clans', ['name', 'server_id']);
        $this->createIndex('idx-clans-tag-server', 'clans', ['tag', 'server_id']);
        $this->createIndex('idx-clans-privacy', 'clans', 'privacy');

        $this->addForeignKey(
            'fk-clans-leader_user_id',
            'clans',
            'leader_user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clans-server_id',
            'clans',
            'server_id',
            'servers',
            'id',
            'CASCADE'
        );

        // Таблица участников клана
        $this->createTable('clan_members', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'user_id' => $userIdType->notNull()->comment('ID пользователя'),
            'role' => "ENUM('member', 'officer', 'leader') DEFAULT 'member' COMMENT 'Роль'",
            'join_date' => 'DATETIME NOT NULL COMMENT \'Дата вступления\'',
            'leave_date' => 'DATETIME DEFAULT NULL COMMENT \'Дата выхода из клана\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_members-clan_id', 'clan_members', 'clan_id');
        $this->createIndex('idx-clan_members-user_id', 'clan_members', 'user_id');
        $this->createIndex('idx-clan_members-clan_user', 'clan_members', ['clan_id', 'user_id']);
        $this->createIndex('idx-clan_members-join_date', 'clan_members', 'join_date');
        $this->createIndex('idx-clan_members-leave_date', 'clan_members', 'leave_date');

        $this->addForeignKey(
            'fk-clan_members-clan_id',
            'clan_members',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_members-user_id',
            'clan_members',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        // Уникальный индекс для активных участников (только если leave_date IS NULL)
        // MySQL не поддерживает частичные индексы напрямую, используем составной индекс
        $this->createIndex('idx-clan_members-active-unique', 'clan_members', ['clan_id', 'user_id', 'leave_date']);

        // Таблица статистики клана (заголовок за вайп; метрики — в clan_statistics_values)
        $this->createTable('clan_statistics', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'server_id' => $serverIdType->notNull()->comment('ID сервера'),
            'wipe' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Дата вайпа\'',
            'last_activity_date' => 'DATETIME DEFAULT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_statistics-clan_id', 'clan_statistics', 'clan_id');
        $this->createIndex('idx-clan_statistics-server_id', 'clan_statistics', 'server_id');
        $this->createIndex('idx-clan_statistics-wipe', 'clan_statistics', 'wipe');
        $this->createIndex('idx-clan_statistics-unique', 'clan_statistics', ['clan_id', 'server_id', 'wipe'], true);

        $this->addForeignKey(
            'fk-clan_statistics-clan_id',
            'clan_statistics',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_statistics-server_id',
            'clan_statistics',
            'server_id',
            'servers',
            'id',
            'CASCADE'
        );

        $this->createTable('clan_statistics_values', [
            'id' => self::PRIMARY_KEY,
            'clan_statistics_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Запись clan_statistics\'',
            'stat_key' => "VARCHAR(80) NOT NULL COMMENT 'Ключ метрики (total_*, top_*, …)'",
            'value' => 'DECIMAL(24, 6) NOT NULL DEFAULT 0 COMMENT \'Значение (целое или дробное для топов)\'',
        ], self::TABLE_OPTIONS);

        $this->createIndex(
            'idx-clan_statistics_values-unique',
            'clan_statistics_values',
            ['clan_statistics_id', 'stat_key'],
            true
        );
        $this->createIndex('idx-clan_statistics_values-key', 'clan_statistics_values', 'stat_key');

        $this->addForeignKey(
            'fk-clan_statistics_values-header',
            'clan_statistics_values',
            'clan_statistics_id',
            'clan_statistics',
            'id',
            'CASCADE'
        );

        // Таблица индивидуальной статистики участников
        $this->createTable('clan_member_statistics', [
            'id' => self::PRIMARY_KEY,
            'clan_member_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID участника клана\'',
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'user_id' => $userIdType->notNull()->comment('ID пользователя'),
            'server_id' => $serverIdType->notNull()->comment('ID сервера'),
            'wipe' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Дата вайпа\'',
            
            // Боевая статистика
            'kills' => 'INT(10) UNSIGNED DEFAULT 0',
            'deaths' => 'INT(10) UNSIGNED DEFAULT 0',
            'scientists' => 'INT(10) UNSIGNED DEFAULT 0',
            'wounded' => 'INT(10) UNSIGNED DEFAULT 0',
            'tcs_destroyed' => 'INT(10) UNSIGNED DEFAULT 0',
            'nude_kills' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Попадания
            'hits_head' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_neck' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_chest' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_lowerspine' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_lefthand' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_leftleg' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_leftfoot' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_righthand' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_rightleg' => 'INT(10) UNSIGNED DEFAULT 0',
            'hits_rightfoot' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Рейдер
            'c4thrown' => 'INT(10) UNSIGNED DEFAULT 0',
            'satchelsthrown' => 'INT(10) UNSIGNED DEFAULT 0',
            'rocket_basic' => 'INT(10) UNSIGNED DEFAULT 0',
            'rocket_hv' => 'INT(10) UNSIGNED DEFAULT 0',
            'rocket_fire' => 'INT(10) UNSIGNED DEFAULT 0',
            'ammo_explosive' => 'INT(10) UNSIGNED DEFAULT 0',
            'grenade_f1_deployed' => 'INT(10) UNSIGNED DEFAULT 0',
            'grenade_molotov_deployed' => 'INT(10) UNSIGNED DEFAULT 0',
            'grenade_beancan_deployed' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Фармер
            'wood' => 'INT(10) UNSIGNED DEFAULT 0',
            'stones' => 'INT(10) UNSIGNED DEFAULT 0',
            'metal_ore' => 'INT(10) UNSIGNED DEFAULT 0',
            'sulfur_ore' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Рыбак
            'f_fish_anchovy' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_catfish' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_herring' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_orangeroughy' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_salmon' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_sardine' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_smallshark' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_troutsmall' => 'INT(10) UNSIGNED DEFAULT 0',
            'f_fish_yellowperch' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Охотник
            'chicken' => 'INT(10) UNSIGNED DEFAULT 0',
            'bear' => 'INT(10) UNSIGNED DEFAULT 0',
            'boar' => 'INT(10) UNSIGNED DEFAULT 0',
            'polarbear' => 'INT(10) UNSIGNED DEFAULT 0',
            'stag' => 'INT(10) UNSIGNED DEFAULT 0',
            'horse' => 'INT(10) UNSIGNED DEFAULT 0',
            'wolf2' => 'INT(10) UNSIGNED DEFAULT 0',
            'wolf' => 'INT(10) UNSIGNED DEFAULT 0',
            'simpleshark' => 'INT(10) UNSIGNED DEFAULT 0',
            'panther' => 'INT(10) UNSIGNED DEFAULT 0',
            'crocodile' => 'INT(10) UNSIGNED DEFAULT 0',
            'tiger' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Фермер
            'gathered_cloth' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_pumpkin' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_corn' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_green_berry' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_blue_berry' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_yellow_berry' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_red_berry' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_white_berry' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_black_berry' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_potato' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_orchid' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_rose' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_sunflower' => 'INT(10) UNSIGNED DEFAULT 0',
            'gathered_wheat' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Другое
            'playtime' => 'INT(10) UNSIGNED DEFAULT 0',
            'crate_open' => 'INT(10) UNSIGNED DEFAULT 0',
            'barrel' => 'INT(10) UNSIGNED DEFAULT 0',
            'helicopters' => 'INT(10) UNSIGNED DEFAULT 0',
            'bradleys' => 'INT(10) UNSIGNED DEFAULT 0',
            'research_table_looted' => 'INT(10) UNSIGNED DEFAULT 0',
            'excavator_mined' => 'INT(10) UNSIGNED DEFAULT 0',
            'raids_completed' => 'INT(10) UNSIGNED DEFAULT 0',
            'raids_defended' => 'INT(10) UNSIGNED DEFAULT 0',
            
            // Расчетные значения
            'top_reider' => 'DECIMAL(10,2) DEFAULT 0',
            'top_kills' => 'DECIMAL(10,2) DEFAULT 0',
            'top_scientists' => 'DECIMAL(10,2) DEFAULT 0',
            'top_playtime' => 'DECIMAL(10,2) DEFAULT 0',
            'top_farmer' => 'DECIMAL(10,2) DEFAULT 0',
            'top_fishing' => 'DECIMAL(10,2) DEFAULT 0',
            'top_hunter' => 'DECIMAL(10,2) DEFAULT 0',
            'top_fermer' => 'DECIMAL(10,2) DEFAULT 0',
            
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_member_statistics-clan_member_id', 'clan_member_statistics', 'clan_member_id');
        $this->createIndex('idx-clan_member_statistics-clan_id', 'clan_member_statistics', 'clan_id');
        $this->createIndex('idx-clan_member_statistics-user_id', 'clan_member_statistics', 'user_id');
        $this->createIndex('idx-clan_member_statistics-server_id', 'clan_member_statistics', 'server_id');
        $this->createIndex('idx-clan_member_statistics-wipe', 'clan_member_statistics', 'wipe');
        $this->createIndex('idx-clan_member_statistics-unique', 'clan_member_statistics', ['clan_member_id', 'server_id', 'wipe'], true);

        $this->addForeignKey(
            'fk-clan_member_statistics-clan_member_id',
            'clan_member_statistics',
            'clan_member_id',
            'clan_members',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_member_statistics-clan_id',
            'clan_member_statistics',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_member_statistics-user_id',
            'clan_member_statistics',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_member_statistics-server_id',
            'clan_member_statistics',
            'server_id',
            'servers',
            'id',
            'CASCADE'
        );

        // Таблица приглашений
        $this->createTable('clan_invites', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'inviter_user_id' => $userIdType->notNull()->comment('ID пригласившего'),
            'invited_user_id' => $userIdType->notNull()->comment('ID приглашенного'),
            'status' => "ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending' COMMENT 'Статус'",
            'expires_at' => 'DATETIME DEFAULT NULL COMMENT \'Дата истечения приглашения\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_invites-clan_id', 'clan_invites', 'clan_id');
        $this->createIndex('idx-clan_invites-inviter_user_id', 'clan_invites', 'inviter_user_id');
        $this->createIndex('idx-clan_invites-invited_user_id', 'clan_invites', 'invited_user_id');
        $this->createIndex('idx-clan_invites-status', 'clan_invites', 'status');
        $this->createIndex('idx-clan_invites-expires_at', 'clan_invites', 'expires_at');

        $this->addForeignKey(
            'fk-clan_invites-clan_id',
            'clan_invites',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_invites-inviter_user_id',
            'clan_invites',
            'inviter_user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_invites-invited_user_id',
            'clan_invites',
            'invited_user_id',
            'user',
            'id',
            'CASCADE'
        );

        // Таблица разрешений
        $this->createTable('clan_permissions', [
            'id' => self::PRIMARY_KEY,
            'key' => 'VARCHAR(50) NOT NULL COMMENT \'Ключ разрешения\'',
            'name' => 'VARCHAR(255) NOT NULL COMMENT \'Название разрешения\'',
            'description' => 'TEXT DEFAULT NULL COMMENT \'Описание разрешения\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_permissions-key', 'clan_permissions', 'key', true);

        // Таблица связи участников и разрешений
        $this->createTable('clan_member_permissions', [
            'id' => self::PRIMARY_KEY,
            'clan_member_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID участника клана\'',
            'permission_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID разрешения\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_member_permissions-clan_member_id', 'clan_member_permissions', 'clan_member_id');
        $this->createIndex('idx-clan_member_permissions-permission_id', 'clan_member_permissions', 'permission_id');
        $this->createIndex('idx-clan_member_permissions-unique', 'clan_member_permissions', ['clan_member_id', 'permission_id'], true);

        $this->addForeignKey(
            'fk-clan_member_permissions-clan_member_id',
            'clan_member_permissions',
            'clan_member_id',
            'clan_members',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_member_permissions-permission_id',
            'clan_member_permissions',
            'permission_id',
            'clan_permissions',
            'id',
            'CASCADE'
        );

        // Таблица событий клана
        $this->createTable('clan_events', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'user_id' => self::INT_FIELD . ' COMMENT \'ID пользователя, связанного с событием\'',
            'event_type' => "VARCHAR(50) NOT NULL COMMENT 'Тип события'",
            'description' => 'TEXT NOT NULL COMMENT \'Описание события\'',
            'metadata' => 'JSON DEFAULT NULL COMMENT \'Дополнительные данные события\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_events-clan_id', 'clan_events', 'clan_id');
        $this->createIndex('idx-clan_events-user_id', 'clan_events', 'user_id');
        $this->createIndex('idx-clan_events-event_type', 'clan_events', 'event_type');
        $this->createIndex('idx-clan_events-created_at', 'clan_events', 'created_at');
        $this->createIndex('idx-clan_events-clan_created', 'clan_events', ['clan_id', 'created_at']);

        $this->addForeignKey(
            'fk-clan_events-clan_id',
            'clan_events',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_events-user_id',
            'clan_events',
            'user_id',
            'user',
            'id',
            'SET NULL'
        );

        // Таблица достижений клана
        $this->createTable('clan_achievements', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'achievement_key' => 'VARCHAR(50) NOT NULL COMMENT \'Ключ достижения\'',
            'name' => 'VARCHAR(255) NOT NULL COMMENT \'Название достижения\'',
            'description' => 'TEXT DEFAULT NULL COMMENT \'Описание достижения\'',
            'icon' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Иконка достижения\'',
            'unlocked_at' => 'INT(10) UNSIGNED NOT NULL COMMENT \'Дата получения достижения\'',
            'metadata' => 'JSON DEFAULT NULL COMMENT \'Дополнительные данные\'',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_achievements-clan_id', 'clan_achievements', 'clan_id');
        $this->createIndex('idx-clan_achievements-achievement_key', 'clan_achievements', 'achievement_key');
        $this->createIndex('idx-clan_achievements-unique', 'clan_achievements', ['clan_id', 'achievement_key'], true);

        $this->addForeignKey(
            'fk-clan_achievements-clan_id',
            'clan_achievements',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        // Таблица рейтингов кланов
        $this->createTable('clan_rankings', [
            'id' => self::PRIMARY_KEY,
            'clan_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID клана\'',
            'server_id' => $serverIdType->notNull()->comment('ID сервера'),
            'ranking_type' => "VARCHAR(50) NOT NULL COMMENT 'Тип рейтинга'",
            'position' => 'INT(10) UNSIGNED DEFAULT 0 COMMENT \'Позиция в рейтинге\'',
            'score' => 'DECIMAL(10,2) DEFAULT 0 COMMENT \'Балл рейтинга\'',
            'period' => "VARCHAR(20) NOT NULL DEFAULT 'all_time' COMMENT 'Период рейтинга'",
            'calculated_at' => 'INT(10) UNSIGNED NOT NULL COMMENT \'Дата расчета рейтинга\'',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-clan_rankings-clan_id', 'clan_rankings', 'clan_id');
        $this->createIndex('idx-clan_rankings-server_id', 'clan_rankings', 'server_id');
        $this->createIndex('idx-clan_rankings-ranking_type', 'clan_rankings', 'ranking_type');
        $this->createIndex('idx-clan_rankings-period', 'clan_rankings', 'period');
        $this->createIndex('idx-clan_rankings-unique', 'clan_rankings', ['clan_id', 'server_id', 'ranking_type', 'period'], true);
        $this->createIndex('idx-clan_rankings-position', 'clan_rankings', ['server_id', 'ranking_type', 'period', 'position']);

        $this->addForeignKey(
            'fk-clan_rankings-clan_id',
            'clan_rankings',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-clan_rankings-server_id',
            'clan_rankings',
            'server_id',
            'servers',
            'id',
            'CASCADE'
        );

        // Заполнение предустановленных разрешений
        $this->batchInsert('clan_permissions', ['key', 'name', 'description', 'created_at'], [
            ['invite', 'Приглашать игроков', 'Возможность приглашать новых участников в клан', time()],
            ['kick', 'Исключать участников', 'Возможность исключать участников из клана', time()],
            ['promote_demote', 'Повышать/понижать участников', 'Возможность изменять роли участников', time()],
            ['edit_clan', 'Редактировать клан', 'Возможность изменять информацию клана (название, тег, девиз, логотип)', time()],
            ['manage_permissions', 'Управлять разрешениями', 'Возможность управлять разрешениями других участников', time()],
        ]);

        // Создание директории для логотипов
        $uploadDir = Yii::getAlias('@frontend/web/uploads/clans');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Проверка и добавление поля created_at в таблицу statistics, если его нет
        $tableSchema = Yii::$app->db->schema->getTableSchema('statistics');
        if ($tableSchema && !isset($tableSchema->columns['created_at'])) {
            $this->addColumn('statistics', 'created_at', 'INT(10) UNSIGNED DEFAULT NULL COMMENT \'Дата создания события\'');
            $this->createIndex('idx-statistics-created_at', 'statistics', 'created_at');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('clan_rankings');
        $this->dropTable('clan_achievements');
        $this->dropTable('clan_events');
        $this->dropTable('clan_member_permissions');
        $this->dropTable('clan_permissions');
        $this->dropTable('clan_invites');
        $this->dropTable('clan_member_statistics');
        $this->dropTable('clan_statistics_values');
        $this->dropTable('clan_statistics');
        $this->dropTable('clan_members');
        $this->dropTable('clans');
        
        // Удаление поля created_at из statistics, если оно было добавлено
        $tableSchema = Yii::$app->db->schema->getTableSchema('statistics');
        if ($tableSchema && isset($tableSchema->columns['created_at'])) {
            $this->dropIndex('idx-statistics-created_at', 'statistics');
            $this->dropColumn('statistics', 'created_at');
        }
    }
    
    /**
     * Определяет тип колонки на основе существующей колонки
     * 
     * @param ColumnSchema|null $column
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function resolveColumnType(?ColumnSchema $column)
    {
        if ($column === null) {
            return $this->integer();
        }

        switch ($column->type) {
            case Schema::TYPE_BIGINT:
                $builder = $this->bigInteger();
                break;
            case Schema::TYPE_SMALLINT:
                $builder = $this->smallInteger();
                break;
            case Schema::TYPE_TINYINT:
                $builder = $this->tinyInteger();
                break;
            default:
                $builder = $this->integer();
        }

        if ($column->unsigned) {
            $builder->unsigned();
        }

        return $builder;
    }
}

