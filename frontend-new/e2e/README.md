# E2E Тесты

Этот каталог содержит автоматические end-to-end тесты для фронтенда.

## Быстрый старт

1. Установите Playwright:
```bash
npm install -D @playwright/test
npx playwright install
```

2. Запустите тесты:
```bash
npm run test:e2e
```

## Структура тестов

- `auth.spec.ts` - Тесты авторизации и аутентификации
- `homepage.spec.ts` - Тесты главной страницы
- `servers.spec.ts` - Тесты страницы серверов
- `blog.spec.ts` - Тесты блога
- `support.spec.ts` - Тесты поддержки
- `api.spec.ts` - Тесты интеграции с API
- `helpers/auth.ts` - Вспомогательные функции для авторизации

## Написание тестов

Каждый тест должен:
1. Быть изолированным (не зависеть от других тестов)
2. Очищать состояние перед выполнением
3. Использовать правильные селекторы (предпочтительно role-based)
4. Обрабатывать асинхронные операции правильно

## Примеры

### Базовый тест

```typescript
import { test, expect } from '@playwright/test';

test('should load page', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('body')).toBeVisible();
});
```

### Тест с авторизацией

```typescript
import { test, expect } from '@playwright/test';
import { loginUser } from './helpers/auth';

test('should show user profile when authenticated', async ({ page }) => {
  await loginUser(page);
  await page.goto('/profile');
  await expect(page.locator('[class*="profile"]')).toBeVisible();
});
```

### Тест с ожиданием элемента

```typescript
test('should display data after loading', async ({ page }) => {
  await page.goto('/');
  // Ждем появления элемента вместо фиксированной задержки
  await page.waitForSelector('[class*="server"]', { timeout: 5000 });
  await expect(page.locator('[class*="server"]').first()).toBeVisible();
});
```




