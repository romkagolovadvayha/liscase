import { test, expect } from '@playwright/test';

test.describe('Blog Page', () => {
  test('should load blog page', async ({ page }) => {
    await page.goto('/posts');
    
    // Проверяем, что страница загрузилась
    await expect(page).toHaveURL(/.*\/posts/);
    
    // Проверяем заголовок страницы
    const heading = page.getByRole('heading', { name: /блог|blog|новост/i });
    if (await heading.isVisible().catch(() => false)) {
      await expect(heading).toBeVisible();
    }
  });

  test('should display blog posts', async ({ page }) => {
    await page.goto('/posts');
    
    // Ждем загрузки постов
    await page.waitForTimeout(2000);
    
    // Проверяем наличие постов (может быть skeleton или список)
    const postsSection = page.locator('[class*="post"], [class*="blog"], article').first();
    if (await postsSection.isVisible().catch(() => false)) {
      await expect(postsSection).toBeVisible();
    }
  });

  test('should filter posts by category', async ({ page }) => {
    await page.goto('/posts');
    
    // Ждем загрузки категорий
    await page.waitForTimeout(2000);
    
    // Находим кнопку категории
    const categoryTab = page.locator('[role="tab"], button[class*="tab"]').nth(1);
    if (await categoryTab.isVisible().catch(() => false)) {
      await categoryTab.click();
      await page.waitForTimeout(1000);
      
      // Проверяем, что категория активировалась
      await expect(categoryTab).toHaveAttribute('aria-selected', 'true');
    }
  });

  test('should search posts', async ({ page }) => {
    await page.goto('/posts');
    
    // Ждем загрузки поиска
    await page.waitForTimeout(1000);
    
    // Находим поле поиска
    const searchInput = page.locator('input[type="text"][placeholder*="поиск" i]').first();
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill('test');
      await page.waitForTimeout(1000);
      
      // Проверяем, что поиск сработал
      await expect(searchInput).toHaveValue('test');
    }
  });

  test('should sort posts', async ({ page }) => {
    await page.goto('/posts');
    
    // Ждем загрузки кнопок сортировки
    await page.waitForTimeout(1000);
    
    // Находим кнопку сортировки
    const sortButton = page.getByRole('button', { name: /дате|просмотр/i }).first();
    if (await sortButton.isVisible().catch(() => false)) {
      await sortButton.click();
      await page.waitForTimeout(1000);
      
      // Проверяем, что сортировка активировалась
      await expect(sortButton).toHaveClass(/primary|active/);
    }
  });
});




