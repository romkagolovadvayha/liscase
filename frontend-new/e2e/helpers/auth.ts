/**
 * Вспомогательные функции для тестирования авторизации
 */

export async function loginUser(page: any, tokens?: { access: string; refresh: string }) {
  const accessToken = tokens?.access || 'test_access_token_' + Date.now();
  const refreshToken = tokens?.refresh || 'test_refresh_token_' + Date.now();
  
  await page.evaluate(({ access, refresh }) => {
    localStorage.setItem('access_token', access);
    localStorage.setItem('refresh_token', refresh);
  }, { access: accessToken, refresh: refreshToken });
  
  await page.reload();
  await page.waitForTimeout(1000);
}

export async function logoutUser(page: any) {
  await page.evaluate(() => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    sessionStorage.clear();
  });
  
  await page.reload();
  await page.waitForTimeout(500);
}

export async function clearStorage(page: any) {
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });
}




