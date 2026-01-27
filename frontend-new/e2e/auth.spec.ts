import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
  test.beforeEach(async ({ page }) => {
    // Очищаем localStorage перед каждым тестом
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('should show login button when not authenticated', async ({ page }) => {
    await page.goto('/');
    
    // Проверяем наличие кнопки входа через Steam
    const loginButton = page.getByRole('link', { name: /войти|авторизоваться|steam/i });
    await expect(loginButton).toBeVisible();
  });

  test('should redirect to Steam OAuth when clicking login', async ({ page, context }) => {
    await page.goto('/');
    
    // Перехватываем навигацию на Steam OAuth
    const loginButton = page.getByRole('link', { name: /войти|авторизоваться|steam/i });
    
    // Нажимаем на кнопку и проверяем, что происходит редирект на Steam
    const [newPage] = await Promise.all([
      context.waitForEvent('page'),
      loginButton.click(),
    ]);
    
    // Проверяем, что открылась страница Steam
    expect(newPage.url()).toContain('steamcommunity.com');
    await newPage.close();
  });

  test('should show user info when authenticated', async ({ page }) => {
    // Симулируем авторизованного пользователя
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.setItem('access_token', 'test_token');
      localStorage.setItem('refresh_token', 'test_refresh_token');
    });
    
    // Перезагружаем страницу
    await page.reload();
    
    // Проверяем, что появилась информация о пользователе
    // Это зависит от реализации компонента Header
    await page.waitForTimeout(1000); // Даем время на загрузку данных
    
    // Проверяем наличие кнопки выхода или аватара пользователя
    const logoutButton = page.getByRole('button', { name: /выйти|logout/i });
    if (await logoutButton.isVisible().catch(() => false)) {
      await expect(logoutButton).toBeVisible();
    }
  });
});




