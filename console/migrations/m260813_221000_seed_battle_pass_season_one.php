<?php

use console\components\migration\Migration;
use yii\helpers\Json;

/**
 * Creates Season 1 and 100 ordered statistic tasks (80 free + 20 VIP extras).
 */
class m260813_221000_seed_battle_pass_season_one extends Migration
{
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        $this->insert('medal', [
            'name' => 'Покоритель первого сезона',
            'description' => 'Медаль за прохождение бесплатной дорожки BATTLE PASS — СЕЗОН 1.',
            'image_path' => '/images/battlepass/season-1-medal-v2.webp',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $medalId = (int)$this->db->getLastInsertID();

        $drops = $this->db->createCommand(
            "SELECT d.id, d.price
             FROM `drop` d
             WHERE d.status = 1
               AND d.market_status = 1
               AND d.drop_type = 0
               AND d.price > 0
               AND EXISTS (SELECT 1 FROM drop_image di WHERE di.drop_id = d.id AND di.type = 1)
             ORDER BY d.price ASC, d.id ASC"
        )->queryAll();

        if (!$drops) {
            throw new RuntimeException('Для Battle Pass не найдено активных товаров с изображениями.');
        }

        $finalReward = $drops[count($drops) - 1];
        $this->insert('battle_pass_season', [
            'name' => 'BATTLE PASS — СЕЗОН 1',
            'slug' => 'season-1',
            'season_number' => 1,
            'description' => 'Пройдите 80 последовательных заданий и получите финальную награду и медаль. Ещё 20 заданий — дополнительная VIP-дорожка.',
            'starts_at' => $now,
            'ends_at' => null,
            'status' => 'active',
            'reward_type' => 'item',
            'reward_item_id' => (int)$finalReward['id'],
            'reward_currency' => null,
            'reward_amount' => 1,
            'medal_id' => $medalId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $seasonId = (int)$this->db->getLastInsertID();

        $profiles = [
            ['title' => 'Лесной промысел', 'key' => 'wood', 'base' => 5000, 'verb' => 'Соберите древесину'],
            ['title' => 'Каменный запас', 'key' => 'stones', 'base' => 5000, 'verb' => 'Добудьте камень'],
            ['title' => 'Железная жила', 'key' => 'metal.ore', 'base' => 4000, 'verb' => 'Добудьте металлическую руду'],
            ['title' => 'Серный след', 'key' => 'sulfur.ore', 'base' => 2000, 'verb' => 'Добудьте серную руду'],
            ['title' => 'Охота за скрапом', 'key' => 'scrap', 'base' => 500, 'verb' => 'Соберите скрап'],
            ['title' => 'Чистая дорога', 'key' => 'barrel', 'base' => 20, 'verb' => 'Разбейте бочки'],
            ['title' => 'Искатель припасов', 'key' => 'crate_open', 'base' => 10, 'verb' => 'Откройте ящики'],
            ['title' => 'Закалка временем', 'key' => 'playtime', 'base' => 60, 'verb' => 'Проведите время на сервере'],
            ['title' => 'Полевая помощь', 'key' => 'bandage', 'base' => 10, 'verb' => 'Используйте бинты'],
            ['title' => 'Зачистка объекта', 'key' => 'scientists', 'base' => 5, 'verb' => 'Уничтожьте учёных'],
            ['title' => 'Боевой опыт', 'key' => 'kills', 'base' => 3, 'verb' => 'Победите игроков'],
            ['title' => 'Точный выстрел', 'key' => 'hits_head', 'base' => 5, 'verb' => 'Совершите попадания в голову'],
            ['title' => 'Охотник на кабанов', 'key' => 'boar', 'base' => 3, 'verb' => 'Добудьте кабанов'],
            ['title' => 'Медвежья тропа', 'key' => 'bear,polarbear', 'base' => 1, 'verb' => 'Добудьте медведей'],
            ['title' => 'Урожай пустоши', 'key' => 'mod_pumpkin', 'base' => 5, 'verb' => 'Соберите тыквы'],
            ['title' => 'Ткацкий запас', 'key' => 'gathered_cloth', 'base' => 200, 'verb' => 'Соберите ткань'],
            ['title' => 'Зелёный доступ', 'key' => 'card_level_1', 'base' => 1, 'verb' => 'Используйте зелёные карты доступа'],
            ['title' => 'Громкий визит', 'key' => 'satchelsthrown', 'base' => 1, 'verb' => 'Используйте сумки с зарядом'],
            ['title' => 'Точный подрыв', 'key' => 'c4thrown', 'base' => 1, 'verb' => 'Используйте взрывчатку C4'],
            ['title' => 'Падение шкафа', 'key' => 'tcsdestroyed', 'base' => 1, 'verb' => 'Уничтожьте шкафы с инструментами'],
        ];
        $tierNames = ['Разведчик', 'Выживший', 'Ветеран', 'Элита', 'Легенда'];
        $multipliers = [1, 3, 7, 14, 24];
        $rows = [];
        $dropCount = count($drops);

        for ($position = 1; $position <= 100; $position++) {
            $profileIndex = ($position - 1) % count($profiles);
            $tier = intdiv($position - 1, count($profiles));
            $profile = $profiles[$profileIndex];
            $required = (int)$profile['base'] * $multipliers[$tier];
            $rewardIndex = (int)floor((($position - 1) * ($dropCount - 1)) / 99);
            $reward = $drops[$rewardIndex];
            $isVip = $position > 80 ? 1 : 0;
            $trackLabel = $isVip ? 'Дополнительное VIP-задание' : 'Задание бесплатной дорожки';
            $formattedRequired = number_format($required, 0, '.', ' ');

            $rows[] = [
                $profile['title'] . ' · ' . $tierNames[$tier],
                $profile['verb'] . ': ' . $formattedRequired,
                $trackLabel . ' №' . $position . '. Прогресс считается только после завершения предыдущего задания.',
                'battle_pass',
                $seasonId,
                $position,
                'statistics_param',
                Json::encode([
                    'stat_key' => $profile['key'],
                    'required_value' => $required,
                    'sum_all_servers' => true,
                ]),
                'item',
                (int)$reward['id'],
                null,
                1,
                null,
                null,
                0,
                $required,
                null,
                'Проверить прогресс',
                Json::encode([]),
                1,
                0,
                $isVip,
                $now,
                $position,
                $now,
                $now,
            ];
        }

        $this->batchInsert('tasks_v2', [
            'title', 'short_description', 'full_description', 'type', 'battle_pass_season_id',
            'battle_pass_position', 'check_type', 'check_params', 'reward_type', 'reward_item_id',
            'reward_currency', 'reward_amount', 'per_user_limit', 'global_limit', 'global_completed',
            'max_progress', 'image_path', 'button_text', 'extra_buttons', 'is_active',
            'is_visible_for_guests', 'is_vip_only', 'available_from', 'sort', 'created_at', 'updated_at',
        ], $rows);
    }

    public function safeDown()
    {
        $seasonId = $this->db->createCommand("SELECT id FROM battle_pass_season WHERE slug = 'season-1'")->queryScalar();
        if ($seasonId) {
            $this->delete('tasks_v2', ['battle_pass_season_id' => (int)$seasonId]);
            $medalId = $this->db->createCommand('SELECT medal_id FROM battle_pass_season WHERE id = :id', [':id' => (int)$seasonId])->queryScalar();
            $this->delete('battle_pass_season', ['id' => (int)$seasonId]);
            if ($medalId) {
                $this->delete('medal', ['id' => (int)$medalId]);
            }
        }
    }
}
