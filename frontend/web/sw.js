/**
 * Service Worker для PWA LiSCase
 * Версия: 1.0.0
 */

const CACHE_NAME = 'liscase-pwa-v1.0.0';
const STATIC_CACHE = 'liscase-static-v1.0.0';
const DYNAMIC_CACHE = 'liscase-dynamic-v1.0.0';

// Файлы для кэширования при установке
const STATIC_FILES = [
  '/',
  '/manifest.json',
  '/offline.html'
];

// Файлы, которые не нужно кэшировать
const EXCLUDE_FILES = [
  '.php',
  'websocket'
];

/**
 * Установка Service Worker
 */
self.addEventListener('install', (event) => {
  console.log('[SW] Installing...');
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[SW] Caching static files');
        // Кэшируем только основные файлы, которые точно существуют
        return cache.addAll([
          '/',
          '/manifest.json'
        ]).catch((error) => {
          console.log('[SW] Some files failed to cache:', error);
          // Продолжаем работу даже если некоторые файлы не кэшировались
          return Promise.resolve();
        });
      })
      .then(() => {
        console.log('[SW] Installation complete');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('[SW] Installation failed:', error);
      })
  );
});

/**
 * Активация Service Worker
 */
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[SW] Activation complete');
        return self.clients.claim();
      })
  );
});

/**
 * Перехват запросов
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Пропускаем не-HTTP запросы
  if (!request.url.startsWith('http')) {
    return;
  }
  
  // Пропускаем исключенные файлы
  if (shouldExcludeFile(request.url)) {
    return;
  }
  
  // Стратегия кэширования в зависимости от типа запроса
  if (request.method === 'GET') {
    event.respondWith(handleRequest(request));
  }
});

/**
 * Обработка запросов с кэшированием
 */
async function handleRequest(request) {
  try {
    // Простая стратегия: сначала пробуем сеть, потом кэш
    try {
      const networkResponse = await fetch(request);
      if (networkResponse.ok) {
        // Кэшируем успешные ответы
        const cache = await caches.open(DYNAMIC_CACHE);
        cache.put(request, networkResponse.clone());
      }
      return networkResponse;
    } catch (error) {
      // Если сеть недоступна, ищем в кэше
      const cachedResponse = await caches.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
      
      // Для HTML страниц возвращаем offline страницу
      if (request.destination === 'document') {
        const offlineResponse = await caches.match('/offline.html');
        if (offlineResponse) {
          return offlineResponse;
        }
      }
      
      throw error;
    }
  } catch (error) {
    console.error('[SW] Request failed:', error);
    throw error;
  }
}

/**
 * Проверка, нужно ли исключить файл из кэширования
 */
function shouldExcludeFile(url) {
  return EXCLUDE_FILES.some(pattern => url.includes(pattern));
}

/**
 * Проверка, является ли ресурс статическим
 */
function isStaticResource(url) {
  const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.woff', '.woff2', '.ttf'];
  return staticExtensions.some(ext => url.includes(ext)) || url.includes('/css/') || url.includes('/js/') || url.includes('/images/');
}

/**
 * Проверка, является ли запрос HTML страницей
 */
function isHTMLRequest(request) {
  return request.destination === 'document' || request.headers.get('accept')?.includes('text/html');
}

/**
 * Проверка, является ли запрос изображением
 */
function isImageRequest(request) {
  return request.destination === 'image' || request.headers.get('accept')?.includes('image/');
}

/**
 * Обработка push уведомлений
 */
self.addEventListener('push', (event) => {
  console.log('[SW] Push received:', event);
  
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'LiSCase', body: event.data.text() };
    }
  }
  
  const options = {
    title: data.title || 'LiSCase',
    body: data.body || 'У вас новое сообщение',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    tag: data.tag || 'default',
    data: data.data || {},
    actions: data.actions || [],
    requireInteraction: data.requireInteraction || false,
    silent: data.silent || false
  };
  
  event.waitUntil(
    self.registration.showNotification(options.title, options)
  );
});

/**
 * Обработка клика по уведомлению
 */
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification click:', event);
  
  event.notification.close();
  
  const urlToOpen = event.notification.data?.url || '/support';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Ищем открытое окно с приложением
        for (const client of clientList) {
          if (client.url.includes(self.location.origin)) {
            client.focus();
            if (client.url !== urlToOpen) {
              client.navigate(urlToOpen);
            }
            return;
          }
        }
        
        // Открываем новое окно если приложение не открыто
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

/**
 * Синхронизация в фоне
 */
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);
  
  if (event.tag === 'background-sync-messages') {
    event.waitUntil(syncMessages());
  }
});

/**
 * Синхронизация сообщений
 */
async function syncMessages() {
  try {
    // Здесь можно добавить логику синхронизации сообщений
    console.log('[SW] Syncing messages...');
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}

/**
 * Обработка ошибок
 */
self.addEventListener('error', (event) => {
  console.error('[SW] Error:', event.error);
});

self.addEventListener('unhandledrejection', (event) => {
  console.error('[SW] Unhandled promise rejection:', event.reason);
});
