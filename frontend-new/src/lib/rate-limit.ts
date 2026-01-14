/**
 * Простой in-memory rate limiter
 * В production следует использовать Redis
 */

interface RateLimitEntry {
  count: number;
  resetAt: number;
}

const rateLimitCache = new Map<string, RateLimitEntry>();

/**
 * Проверяет rate limit для пользователя
 * @param key Уникальный ключ (например, userId + action)
 * @param maxRequests Максимальное количество запросов
 * @param windowMs Окно времени в миллисекундах
 * @returns true если лимит не превышен, false если превышен
 */
export function checkRateLimit(
  key: string,
  maxRequests: number = 5,
  windowMs: number = 5000
): boolean {
  const now = Date.now();
  const entry = rateLimitCache.get(key);

  if (!entry || now > entry.resetAt) {
    // Создаем новую запись
    rateLimitCache.set(key, {
      count: 1,
      resetAt: now + windowMs,
    });
    return true;
  }

  if (entry.count >= maxRequests) {
    return false;
  }

  // Увеличиваем счетчик
  entry.count++;
  return true;
}

/**
 * Очищает старые записи из кэша
 */
export function cleanupRateLimitCache() {
  const now = Date.now();
  for (const [key, entry] of rateLimitCache.entries()) {
    if (now > entry.resetAt) {
      rateLimitCache.delete(key);
    }
  }
}

// Очищаем кэш каждые 5 минут
if (typeof setInterval !== 'undefined') {
  setInterval(cleanupRateLimitCache, 5 * 60 * 1000);
}







