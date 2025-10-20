// Упрощенный Service Worker для отладки
console.log('[SW-DEBUG] Service Worker загружен');

const CACHE_NAME = 'liscase-debug-v1';

// Установка
self.addEventListener('install', event => {
  console.log('[SW-DEBUG] Установка');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW-DEBUG] Кэш открыт');
        return cache.addAll([
          '/',
          '/manifest.json'
        ]).catch(error => {
          console.log('[SW-DEBUG] Ошибка кэширования:', error);
          return Promise.resolve();
        });
      })
      .then(() => {
        console.log('[SW-DEBUG] Установка завершена');
        return self.skipWaiting();
      })
  );
});

// Активация
self.addEventListener('activate', event => {
  console.log('[SW-DEBUG] Активация');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      console.log('[SW-DEBUG] Найдены кэши:', cacheNames);
      return Promise.resolve();
    }).then(() => {
      console.log('[SW-DEBUG] Активация завершена');
      return self.clients.claim();
    })
  );
});

// Fetch
self.addEventListener('fetch', event => {
  console.log('[SW-DEBUG] Fetch:', event.request.url);
  
  event.respondWith(
    fetch(event.request)
      .then(response => {
        console.log('[SW-DEBUG] Сетевой ответ:', response.status);
        return response;
      })
      .catch(error => {
        console.log('[SW-DEBUG] Ошибка сети:', error);
        return caches.match(event.request).then(response => {
          if (response) {
            console.log('[SW-DEBUG] Найден в кэше');
            return response;
          }
          console.log('[SW-DEBUG] Не найдено в кэше');
          throw error;
        });
      })
  );
});

console.log('[SW-DEBUG] Service Worker инициализирован');



