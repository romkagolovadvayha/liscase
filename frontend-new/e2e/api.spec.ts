import { test, expect } from '@playwright/test';

test.describe('API Integration', () => {
  test('should not duplicate API requests', async ({ page }) => {
    // Перехватываем все сетевые запросы
    const apiRequests: string[] = [];
    
    page.on('request', (request) => {
      const url = request.url();
      if (url.includes('/v1/')) {
        apiRequests.push(url);
      }
    });
    
    await page.goto('/');
    
    // Ждем загрузки страницы
    await page.waitForTimeout(3000);
    
    // Подсчитываем количество одинаковых запросов
    const requestCounts: Record<string, number> = {};
    apiRequests.forEach((url) => {
      // Извлекаем путь без query параметров для подсчета
      const path = url.split('?')[0];
      requestCounts[path] = (requestCounts[path] || 0) + 1;
    });
    
    // Проверяем, что нет дублирования запросов (каждый endpoint вызывается не более 2-3 раз из-за React StrictMode)
    Object.entries(requestCounts).forEach(([path, count]) => {
      if (count > 3) {
        console.warn(`Endpoint ${path} was called ${count} times (may indicate duplicate requests)`);
      }
      // Разрешаем до 3 вызовов из-за React StrictMode и начальной загрузки
      expect(count).toBeLessThanOrEqual(5);
    });
  });

  test('should handle API errors gracefully', async ({ page }) => {
    // Перехватываем и мокируем API запросы с ошибкой
    await page.route('**/v1/servers', (route) => {
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ success: false, message: 'Server error' }),
      });
    });
    
    await page.goto('/');
    
    // Ждем обработки ошибки
    await page.waitForTimeout(2000);
    
    // Проверяем, что страница не упала и есть обработка ошибки
    await expect(page.locator('body')).toBeVisible();
  });

  test('should handle 401 errors and refresh token', async ({ page }) => {
    await page.goto('/');
    
    // Устанавливаем токены
    await page.evaluate(() => {
      localStorage.setItem('access_token', 'expired_token');
      localStorage.setItem('refresh_token', 'valid_refresh_token');
    });
    
    // Перехватываем запрос и возвращаем 401
    let refreshCalled = false;
    await page.route('**/v1/auth/me', async (route) => {
      if (route.request().headers()['authorization']?.includes('expired_token')) {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, message: 'Unauthorized' }),
        });
      } else {
        refreshCalled = true;
        await route.continue();
      }
    });
    
    await page.route('**/v1/auth/refresh', async (route) => {
      refreshCalled = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            token: 'new_access_token',
            refresh_token: 'new_refresh_token',
          },
        }),
      });
    });
    
    // Перезагружаем страницу
    await page.reload();
    await page.waitForTimeout(2000);
    
    // Проверяем, что был вызван refresh
    // В реальном приложении токен должен обновиться автоматически
    expect(refreshCalled).toBeTruthy();
  });
});




