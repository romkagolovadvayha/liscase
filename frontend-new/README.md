# Frontend New - React SSR Application

Новый frontend на Next.js с серверным рендерингом.

## Установка

```bash
npm install
# или
yarn install
# или
pnpm install
```

## Разработка

```bash
npm run dev
# или
yarn dev
# или
pnpm dev
```

Откройте [http://localhost:3000](http://localhost:3000) в браузере.

## Структура проекта

- `src/app/` - Next.js App Router (страницы и роуты)
- `src/components/` - React компоненты
- `src/lib/` - Утилиты, API клиент, хелперы
- `src/styles/` - Глобальные стили
- `src/types/` - TypeScript типы
- `public/` - Статические файлы

## API

API запросы проксируются на `/api/v1/*` который идет на Yii2 backend.

## Документация

См. [MIGRATION_PLAN.md](../MIGRATION_PLAN.md) для подробного плана миграции.



















