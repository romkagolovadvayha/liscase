/**
 * Service Worker для PWA LiSCase
 * Версия: 1.0.0
 */

const CACHE_NAME = 'liscase-pwa-v1.0.0';
const STATIC_CACHE = 'liscase-static-v1.0.0';
const DYNAMIC_CACHE = 'liscase-dynamic-v1.0.0';
const DEBUG = ['localhost', '127.0.0.1'].includes(self.location.hostname);
const log = (...args) => { if (DEBUG) { console.log('[SW]', ...args); } };
const logError = (...args) => console.error('[SW]', ...args);

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

// Пути, которые Service Worker не должен перехватывать (потоковые запросы)
const EXCLUDE_PATHS = [
  '/station-1/stream',
  '/station-2/stream',
  '/station-3/stream',
  '/ws/', // WebSocket
];

/**
 * Установка Service Worker
 */
self.addEventListener('install', (event) => {
  log('Installing...');
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        log('Caching static files');
        // Кэшируем только основные файлы, которые точно существуют
        return cache.addAll([
          '/',
          '/manifest.json'
        ]).catch((error) => {
          log('Some files failed to cache:', error);
          // Продолжаем работу даже если некоторые файлы не кэшировались
          return Promise.resolve();
        });
      })
      .then(() => {
        log('Installation complete');
        return self.skipWaiting();
      })
      .catch((error) => {
        logError('Installation failed:', error);
      })
  );
});

/**
 * Активация Service Worker
 */
self.addEventListener('activate', (event) => {
  log('Activating...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
              log('Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        log('Activation complete');
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
  
  // Пропускаем исключенные файлы и пути (потоковые запросы, WebSocket)
  if (shouldExcludeFile(request.url)) {
    return;
  }
  
  // Пропускаем потоковые запросы к радиостанциям и WebSocket
  if (shouldExcludePath(request.url)) {
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
    logError('Request failed:', error);
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
 * Проверка, нужно ли исключить путь из перехвата (потоковые запросы)
 */
function shouldExcludePath(url) {
  return EXCLUDE_PATHS.some(path => url.includes(path));
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
  log('Push received:', event);
  
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
    badge: '/icons/icon-72x72.png',
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
  log('Notification click:', event);
  
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
  log('Background sync:', event.tag);
  
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
    log('Syncing messages...');
  } catch (error) {
    logError('Sync failed:', error);
  }
}

/**
 * Обработка ошибок
 */
self.addEventListener('error', (event) => {
  logError('Error:', event.error);
});

self.addEventListener('unhandledrejection', (event) => {
  logError('Unhandled promise rejection:', event.reason);
});
