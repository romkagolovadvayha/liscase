# План реализации "Маркет скинов"

## Проблемы старой версии:
1. ❌ Каждый запрос обрабатывает весь JSON файл
2. ❌ Кеш всего на 60 секунд
3. ❌ Нет БД для хранения каталога
4. ❌ Медленная фильтрация и сортировка
5. ❌ Нет возможности управлять видимостью скинов

## Новое решение:

### 1. Структура базы данных

```sql
CREATE TABLE `market_skins` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `instance_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  `market_hash_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ru_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `ru_quality` varchar(50) DEFAULT NULL,
  `text_color` varchar(10) DEFAULT NULL,
  `bg_color` varchar(10) DEFAULT NULL,
  `price` decimal(10,2) UNSIGNED NOT NULL COMMENT 'Цена с rust.tm (в копейках)',
  `our_price` int(11) UNSIGNED NOT NULL COMMENT 'Наша цена с накруткой (в копейках)',
  `markup_percent` decimal(5,2) UNSIGNED NOT NULL DEFAULT '30.00' COMMENT 'Процент накрутки',
  `avg_price` decimal(10,2) DEFAULT NULL,
  `popularity_7d` int(11) DEFAULT '0',
  `image_url` varchar(500) DEFAULT NULL,
  `image300_url` varchar(500) DEFAULT NULL,
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-активен, 0-неактивен',
  `is_stat_trak` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `last_synced_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_instance` (`class_id`, `instance_id`),
  KEY `idx_market_hash_name` (`market_hash_name`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_our_price` (`our_price`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Архитектура решения

#### A. Синхронизация данных (Cron Job / API endpoint)
- Периодически (например, каждые 5-10 минут) загружает данные из `https://rust.tm/api/v2/prices/RUB.json`
- Обрабатывает и фильтрует скины по тем же правилам
- Сохраняет/обновляет в БД с накруткой
- Использует `ON DUPLICATE KEY UPDATE` для быстрого обновления

#### B. API Endpoints
1. `GET /api/market/skins` - список скинов (с пагинацией, фильтрацией, сортировкой)
2. `GET /api/market/skins/:id` - детали скина
3. `POST /api/market/skins/sync` - ручная синхронизация (для админов)
4. `GET /api/market/skins/categories` - список категорий

#### C. Покупка скина
- Использует существующий API rust.tm `/api/v2/buy-for`
- Сохраняет в `user_payout_skins` (как в старой версии)

### 3. Преимущества нового подхода:
✅ Быстрые запросы (данные из БД)
✅ Эффективная фильтрация и сортировка через SQL
✅ Возможность управления статусом скинов
✅ История изменений цен
✅ Настраиваемая накрутка на разные скины
✅ Кеширование на уровне БД

### 4. Производительность:
- Список скинов: < 50ms (из БД с индексами)
- Синхронизация: 1-2 минуты (в фоне)
- Обновление цен: только измененные записи














