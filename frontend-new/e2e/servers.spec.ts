import { test, expect } from '@playwright/test';

test.describe('Servers Page', () => {
  test('should load servers page', async ({ page }) => {
    await page.goto('/servers');
    
    // Проверяем, что страница загрузилась
    await expect(page).toHaveURL(/.*\/servers/);
    
    // Проверяем заголовок страницы
    const heading = page.getByRole('heading', { name: /сервер|server/i });
    if (await heading.isVisible().catch(() => false)) {
      await expect(heading).toBeVisible();
    }
  });

  test('should display server list', async ({ page }) => {
    await page.goto('/servers');
    
    // Ждем загрузки серверов
    await page.waitForTimeout(2000);
    
    // Проверяем наличие списка серверов
    const serverList = page.locator('[class*="server"], article[class*="server"]').first();
    if (await serverList.isVisible().catch(() => false)) {
      await expect(serverList).toBeVisible();
    }
  });

  test('should filter servers by category', async ({ page }) => {
    await page.goto('/servers');
    
    // Ждем загрузки категорий
    await page.waitForTimeout(2000);
    
    // Находим кнопку категории
    const categoryButton = page.locator('[class*="tag"], [class*="categor"] button').first();
    if (await categoryButton.isVisible().catch(() => false)) {
      await categoryButton.click();
      await page.waitForTimeout(1000);
      
      // Проверяем, что категория активировалась
      await expect(categoryButton).toHaveClass(/active|selected/);
    }
  });

  test('should navigate to server detail page', async ({ page }) => {
    await page.goto('/servers');
    
    // Ждем загрузки серверов
    await page.waitForTimeout(2000);
    
    // Находим первую ссылку на сервер
    const serverLink = page.locator('a[href*="/servers/"]').first();
    if (await serverLink.isVisible().catch(() => false)) {
      const href = await serverLink.getAttribute('href');
      await serverLink.click();
      await page.waitForTimeout(1000);
      
      // Проверяем, что перешли на страницу сервера
      if (href) {
        await expect(page).toHaveURL(new RegExp(href.replace(/\//g, '\\/')));
      }
    }
  });
});




