# Настройка маркета скинов

## Шаги по развертыванию:

### 1. Создание таблицы в БД

Выполните SQL миграцию:
```bash
mysql -u your_user -p your_database < database/migrations/create_market_skins.sql
```

Или выполните SQL напрямую из файла `database/migrations/create_market_skins.sql`

### 2. Первая синхронизация

Запустите синхронизацию вручную (через API или скрипт):

**Через API:**
```bash
curl -X POST http://localhost:3000/api/market/skins/sync
```

**Или через браузер:**
Откройте `http://localhost:3000/api/market/skins/sync` с методом POST

### 3. Настройка автоматической синхронизации

#### Вариант A: Cron Job (рекомендуется)

Добавьте в crontab:
```bash
# Синхронизация каждые 10 минут
*/10 * * * * curl -X POST http://localhost:3000/api/market/skins/sync > /dev/null 2>&1
```

#### Вариант B: Node.js Cron (в коде)

Создайте файл `frontend-new/src/server/skins-sync-cron.ts`:

```typescript
import cron from 'node-cron';

// Запуск каждые 10 минут
cron.schedule('*/10 * * * *', async () => {
  try {
    const response = await fetch('http://localhost:3000/api/market/skins/sync', {
      method: 'POST',
    });
    const result = await response.json();
    console.log('Skins sync completed:', result);
  } catch (error) {
    console.error('Skins sync error:', error);
  }
});
```

### 4. API Endpoints

#### Получить список скинов
```
GET /api/market/skins?page=1&limit=50&category=Weapon&sort=our_price&order=asc
```

Параметры:
- `page` - номер страницы (по умолчанию 1)
- `limit` - количество на странице (по умолчанию 50)
- `category` - фильтр по категории
- `search` - поиск по названию
- `sort` - поле сортировки (our_price, name, popularity_7d, created_at)
- `order` - порядок сортировки (asc, desc)
- `minPrice` - минимальная цена (в рублях)
- `maxPrice` - максимальная цена (в рублях)

#### Получить категории
```
GET /api/market/skins/categories
```

#### Синхронизация (для админов)
```
POST /api/market/skins/sync
```

### 5. Производительность

- **Синхронизация**: ~1-2 минуты (зависит от количества скинов)
- **Получение списка**: < 50ms (с индексами в БД)
- **Фильтрация**: < 100ms

### 6. Настройка накрутки

В файле `src/app/api/market/skins/sync/route.ts` измените:
```typescript
const MARKUP_PERCENT = 30; // Процент накрутки (30% = 1.3x)
```

Для разных скинов можно сделать разную накрутку, добавив логику в цикл обработки.

### 7. Мониторинг

После синхронизации API возвращает статистику:
```json
{
  "success": true,
  "stats": {
    "inserted": 150,
    "updated": 1200,
    "skipped": 50,
    "total": 1400
  }
}
```

### 8. Пример использования в компоненте

```typescript
// Получение списка скинов
const response = await fetch('/api/market/skins?page=1&limit=20&category=Weapon');
const data = await response.json();
const skins = data.data.items;

// Каждый скин содержит:
// {
//   id: number,
//   name: string,
//   ru_name: string,
//   our_price: number, // в рублях
//   image_url: string,
//   category: string,
//   ...
// }
```














