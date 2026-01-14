# Миграция на новое API

## Выполнено ✅

1. ✅ Удалены все API routes из `src/app/api` (62 файла)
2. ✅ Удален серверный код из `src/server/`
3. ✅ Удалены файлы:
   - `src/lib/db.ts` - подключение к БД (не нужно во frontend)
   - `src/lib/server-stats.ts` - серверная статистика (нужно переписать на API)
   - `src/lib/profile.ts` - серверные функции профиля (нужно переписать на API)
   - `src/lib/auth/steam.ts` - создание пользователей через Steam (не нужно, это делается в API)
4. ✅ Создан новый API клиент в `src/lib/api/client.ts` с поддержкой JWT токенов
5. ✅ Создан модуль авторизации в `src/lib/api/auth.ts`
6. ✅ Переписан `src/lib/services/settings.ts` для использования нового API
7. ✅ Обновлен `package.json` - убраны скрипты для WebSocket сервера
8. ✅ Обновлен `next.config.js` - изменена переменная окружения на `NEXT_PUBLIC_API_BASE_URL`

## Требует обновления ⚠️

### Файлы, которые используют удаленные функции:

1. **Страницы, которые используют `getServerStatsData`** (из удаленного `lib/server-stats.ts`):
   - `src/app/servers/[tag]/page.tsx`
   - `src/app/servers/page.tsx`
   - Другие файлы, которые импортируют эту функцию
   
   **Решение**: Использовать API endpoint `/v1/stats`

2. **Страницы, которые используют `getPlayerProfileData`** (из удаленного `lib/profile.ts`):
   - `src/app/profile/[steamId]/page.tsx`
   - Другие файлы, которые импортируют эту функцию
   
   **Решение**: Использовать API endpoint `/v1/stats/player/{steamId}`

3. **Файлы, которые используют `getSettings`**:
   - `src/app/layout.tsx`
   - `src/app/page.tsx`
   - Другие файлы
   
   **Статус**: ✅ Уже обновлено - теперь использует API

### Hooks и компоненты:

1. Проверить все hooks в `src/hooks/` - обновить для использования нового API
2. Проверить все компоненты - убрать прямые вызовы к `/api/*` routes

## Настройка окружения

Добавьте в `.env.local`:
```
NEXT_PUBLIC_API_BASE_URL=http://api.test.prostoj.store
```

## API Endpoints

Новое API находится по адресу: `http://api.test.prostoj.store/v1/`

Документация Swagger: `http://api.test.prostoj.store/swagger`

### Основные endpoints:

- **Auth**: `/v1/auth/*` (oauth, callback, login, refresh, logout, me)
- **User**: `/v1/user/*` (profile, balance, history, partner, etc.)
- **Servers**: `/v1/servers/*` (index, view, tag, rules, wipe-info)
- **Stats**: `/v1/stats/*` (stats, player, search, tops, personal, report)
- **Tasks**: `/v1/tasks/*` (index, detail, check)
- **Payment**: `/v1/payment/*` (methods, create, status, callback)
- **Support**: `/v1/support/*` (tickets, view, create, send, close, open)
- **Skins**: `/v1/skins/*` (index, confirm)
- **Settings**: `/v1/settings`

## Следующие шаги

1. Обновить страницы, которые используют `getServerStatsData` и `getPlayerProfileData`
2. Обновить hooks для использования нового API
3. Проверить и обновить все компоненты
4. Удалить неиспользуемые зависимости (mysql2, bcryptjs, ws, tsx, etc.)
5. Протестировать все функции




