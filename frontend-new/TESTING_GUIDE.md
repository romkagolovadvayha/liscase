# Руководство по автоматическому тестированию

## Установка Playwright

Для установки Playwright выполните:

```bash
npm install -D @playwright/test
npx playwright install
```

## Запуск тестов

### Запуск всех тестов

```bash
npm run test:e2e
```

### Запуск тестов в конкретном браузере

```bash
npm run test:e2e:chromium
npm run test:e2e:firefox
npm run test:e2e:webkit
```

### Запуск тестов в UI режиме (рекомендуется для разработки)

```bash
npm run test:e2e:ui
```

### Запуск тестов в режиме отладки

```bash
npm run test:e2e:debug
```

### Запуск конкретного теста

```bash
npx playwright test e2e/auth.spec.ts
```

### Запуск тестов с генерированием отчета

```bash
npm run test:e2e:report
```

## Структура тестов

```
e2e/
  ├── auth.spec.ts          # Тесты авторизации
  ├── homepage.spec.ts      # Тесты главной страницы
  ├── servers.spec.ts       # Тесты страницы серверов
  ├── blog.spec.ts          # Тесты блога
  ├── support.spec.ts       # Тесты поддержки
  ├── api.spec.ts           # Тесты интеграции с API
  └── helpers/
      └── auth.ts           # Вспомогательные функции
```

## Настройка окружения

Создайте файл `.env.local` для настройки тестового окружения:

```env
PLAYWRIGHT_BASE_URL=http://localhost:3000
NEXT_PUBLIC_API_BASE_URL=http://api.test.prostoj.store
```

## Написание новых тестов

### Базовый пример

```typescript
import { test, expect } from '@playwright/test';

test('my test', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Welcome' })).toBeVisible();
});
```

### Использование вспомогательных функций

```typescript
import { test, expect } from '@playwright/test';
import { loginUser, logoutUser } from './helpers/auth';

test('authenticated user test', async ({ page }) => {
  await loginUser(page);
  await page.goto('/profile');
  // Ваши тесты
  await logoutUser(page);
});
```

## Отладка тестов

### Запуск с отладкой

```bash
npm run test:e2e:debug
```

### Скриншоты и видео

Playwright автоматически сохраняет скриншоты при падении тестов в директории `test-results/`.

### Трейсинг

Playwright может записывать трейс выполнения теста для отладки:

```typescript
test('my test', async ({ page, context }) => {
  await context.tracing.start({ screenshots: true, snapshots: true });
  // Ваши тесты
  await context.tracing.stop({ path: 'trace.zip' });
});
```

## CI/CD интеграция

Для запуска тестов в CI/CD добавьте в ваш workflow:

```yaml
- name: Install Playwright Browsers
  run: npx playwright install --with-deps

- name: Run Playwright tests
  run: npm run test:e2e
  env:
    CI: true
```

## Известные проблемы

1. **React StrictMode**: В режиме разработки React StrictMode может вызывать двойные рендеринги, что приводит к двойным API запросам. Это нормальное поведение.

2. **Timing**: Некоторые тесты могут быть чувствительны к времени загрузки. Используйте `waitForTimeout` или лучше `waitForSelector` для ожидания элементов.

3. **Авторизация**: Для тестирования авторизованных пользователей используйте мокирование токенов в localStorage.

## Дополнительные ресурсы

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Playwright API Reference](https://playwright.dev/docs/api/class-playwright)




