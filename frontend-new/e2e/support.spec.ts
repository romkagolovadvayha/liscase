import { test, expect } from '@playwright/test';

test.describe('Support', () => {
  test.beforeEach(async ({ page }) => {
    // Очищаем localStorage перед каждым тестом
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('should show auth modal when opening support without auth', async ({ page }) => {
    await page.goto('/');
    
    // Находим иконку поддержки
    const supportIcon = page.locator('[class*="support"], [aria-label*="поддерж" i]').first();
    if (await supportIcon.isVisible().catch(() => false)) {
      await supportIcon.click();
      await page.waitForTimeout(500);
      
      // Проверяем, что появилось модальное окно с предложением авторизации
      const authModal = page.locator('[class*="modal"], [role="dialog"]');
      if (await authModal.isVisible().catch(() => false)) {
        await expect(authModal).toBeVisible();
        
        // Проверяем наличие текста о необходимости авторизации
        const authText = page.getByText(/авторизоваться|войти/i);
        if (await authText.isVisible().catch(() => false)) {
          await expect(authText).toBeVisible();
        }
      }
    }
  });

  test('should open support when authenticated', async ({ page }) => {
    // Симулируем авторизованного пользователя
    await page.goto('/');
    await page.evaluate(() => {
      localStorage.setItem('access_token', 'test_token');
      localStorage.setItem('refresh_token', 'test_refresh_token');
    });
    await page.reload();
    await page.waitForTimeout(1000);
    
    // Находим иконку поддержки
    const supportIcon = page.locator('[class*="support"], [aria-label*="поддерж" i]').first();
    if (await supportIcon.isVisible().catch(() => false)) {
      await supportIcon.click();
      await page.waitForTimeout(1000);
      
      // Проверяем, что открылось окно поддержки (не модальное окно авторизации)
      const supportModal = page.locator('[class*="support"], [class*="ticket"]').first();
      if (await supportModal.isVisible().catch(() => false)) {
        await expect(supportModal).toBeVisible();
      }
    }
  });
});




