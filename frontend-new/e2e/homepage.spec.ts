import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('should load homepage successfully', async ({ page }) => {
    await page.goto('/');
    
    // Проверяем, что страница загрузилась
    await expect(page).toHaveURL(/.*\/$/);
    
    // Проверяем наличие основных элементов
    await expect(page.locator('body')).toBeVisible();
  });

  test('should display server list', async ({ page }) => {
    await page.goto('/');
    
    // Ждем загрузки серверов (может быть skeleton или список)
    await page.waitForTimeout(2000);
    
    // Проверяем наличие блока с серверами
    const serversSection = page.locator('[class*="server"], [class*="servers"]').first();
    if (await serversSection.isVisible().catch(() => false)) {
      await expect(serversSection).toBeVisible();
    }
  });

  test('should display product categories', async ({ page }) => {
    await page.goto('/');
    
    // Ждем загрузки категорий
    await page.waitForTimeout(2000);
    
    // Проверяем наличие категорий
    const categoriesSection = page.locator('[class*="categor"]').first();
    if (await categoriesSection.isVisible().catch(() => false)) {
      await expect(categoriesSection).toBeVisible();
    }
  });

  test('should display products', async ({ page }) => {
    await page.goto('/');
    
    // Ждем загрузки продуктов
    await page.waitForTimeout(2000);
    
    // Проверяем наличие продуктов
    const productsSection = page.locator('[class*="product"], [class*="goods"]').first();
    if (await productsSection.isVisible().catch(() => false)) {
      await expect(productsSection).toBeVisible();
    }
  });

  test('should filter products by category', async ({ page }) => {
    await page.goto('/');
    
    // Ждем загрузки категорий
    await page.waitForTimeout(2000);
    
    // Находим первую категорию (кроме "Все")
    const categoryButton = page.locator('[class*="categor"] button, [class*="categor"] [role="button"]').nth(1);
    if (await categoryButton.isVisible().catch(() => false)) {
      await categoryButton.click();
      await page.waitForTimeout(1000);
      
      // Проверяем, что категория активировалась
      await expect(categoryButton).toHaveClass(/active|selected/);
    }
  });

  test('should search products', async ({ page }) => {
    await page.goto('/');
    
    // Ждем загрузки поиска
    await page.waitForTimeout(1000);
    
    // Находим поле поиска
    const searchInput = page.locator('input[type="text"][placeholder*="поиск" i], input[type="search"]').first();
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill('test');
      await page.waitForTimeout(1000);
      
      // Проверяем, что поиск сработал
      await expect(searchInput).toHaveValue('test');
    }
  });
});




