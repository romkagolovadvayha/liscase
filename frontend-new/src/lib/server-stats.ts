import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';

// In-memory кэш для статистики серверов
const cache = new Map<string, any>();
const cacheExpiry = new Map<string, number>();

// Время жизни кэша: 1 час (3600 секунд)
const CACHE_TTL = 3600 * 1000; // в миллисекундах

// Функция для очистки устаревших записей из кэша
function cleanExpiredCache() {
  const now = Date.now();
  for (const [key, expiry] of cacheExpiry.entries()) {
    if (now > expiry) {
      cache.delete(key);
      cacheExpiry.delete(key);
    }
  }
}

// Функция для генерации ключа кэша
function getCacheKey(tag: string, wipe?: string): string {
  return wipe ? `stats:${tag}:${wipe}` : `stats:${tag}:default`;
}

const TOP_TYPES = {
  reider: 'Лучший рейдер',
  kills: 'Лучший киллер',
  scientists: 'Лучший мирный',
  playtime: 'Топ по онлайну',
  farmer: 'Лучший фармер',
  fishing: 'Лучший рыбак',
  hunter: 'Лучший охотник',
  fermer: 'Лучший фермер',
};

const getPositionColor = (position: number): string => {
  switch (position) {
    case 0:
      return 'gold';
    case 1:
      return 'silver';
    case 2:
      return 'bronze';
    default:
      return '';
  }
};

// Функция для получения награды за позицию (будет переопределена после получения настроек)
let getPositionAmount = (position: number, settings?: Record<string, any>): number => {
  if (settings) {
    switch (position) {
      case 0:
        return Number(settings.tops_gold_amount || 1000);
      case 1:
        return Number(settings.tops_silver_amount || 500);
      case 2:
        return Number(settings.tops_bronze_amount || 250);
      default:
        return 0;
    }
  }
  // Fallback значения
  switch (position) {
    case 0:
      return 1000;
    case 1:
      return 500;
    case 2:
      return 250;
    default:
      return 0;
  }
};

const formatPlayTime = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  if (hours > 0) {
    return `${hours}ч ${minutes}м`;
  }
  return `${minutes}м`;
};

// Функция для форматирования аватара (как в Header)
const formatAvatar = (avatar: string | null, cdnUrl: string): string => {
  const defaultAvatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';
  
  if (!avatar || !avatar.trim()) {
    const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
    return `${baseUrl}${defaultAvatar}`;
  }
  
  const avatarPath = avatar.trim();
  if (avatarPath.startsWith('http://') || avatarPath.startsWith('https://')) {
    return avatarPath;
  }
  
  // Используем CDN_URL напрямую, как в Header
  const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
  return `${baseUrl}${avatarPath.startsWith('/') ? avatarPath : '/' + avatarPath}`;
};

// Защита от параллельных вызовов (Promise мемоизация)
const pendingRequests = new Map<string, Promise<any>>();

export async function getServerStatsData(tag: string, wipe?: string, currentUserSteamId?: string) {
  const startTime = Date.now();
  const cacheKey = getCacheKey(tag, wipe);
  
  // Получаем stack trace для отладки
  const stack = new Error().stack;
  const caller = stack?.split('\n')[3]?.trim() || 'unknown';
  console.log(`[getServerStatsData] START for tag: ${tag}, wipe: ${wipe || 'default'}, caller: ${caller}`);
  
  // Очищаем устаревшие записи (делаем один раз перед всеми проверками)
  cleanExpiredCache();
  
  // Проверяем кэш ПЕРЕД проверкой pending requests
  // НО: userTops зависит от пользователя, поэтому не возвращаем кэш, если нужны позиции пользователя
  const cached = cache.get(cacheKey);
  const expiry = cacheExpiry.get(cacheKey);
  if (cached && expiry && Date.now() < expiry && !currentUserSteamId) {
    console.log(`[getServerStatsData] Cache hit for ${cacheKey}, total time: ${Date.now() - startTime}ms`);
    return cached;
  }
  
  // Если есть кэш, но нужны позиции пользователя, используем кэш, но пересчитываем userTops
  let cachedData = null;
  if (cached && expiry && Date.now() < expiry) {
    cachedData = cached;
    console.log(`[getServerStatsData] Cache hit for ${cacheKey}, but need to recalculate userTops`);
  }
  
  // Проверяем, есть ли уже выполняющийся запрос
  const pending = pendingRequests.get(cacheKey);
  if (pending) {
    console.log(`[getServerStatsData] Reusing pending request for ${cacheKey}`);
    return pending;
  }
  
  // Создаем Promise и сохраняем его перед началом выполнения
  const requestPromise = (async () => {
    try {
      // Если есть кэшированные данные, используем их, но пересчитываем userTops
      if (cachedData && currentUserSteamId) {
        console.log(`[getServerStatsData] Using cached data, recalculating userTops for steam_id: ${currentUserSteamId}`);
        
        // Получаем позиции пользователя (используем тот же подход, что и в основной функции)
        const userTops: Record<string, { position: number }> = {};
        const keyColumn = '`key`';
        const userPositions = await query<any>(`
          SELECT 
            ut_user.${keyColumn} as key_col,
            (SELECT COUNT(*) + 1
             FROM user_top ut_count
             INNER JOIN user u_count ON ut_count.user_id = u_count.id AND u_count.is_stats = 1
             WHERE ut_count.server_id = ?
               AND ut_count.wipe = ?
               AND ut_count.${keyColumn} = ut_user.${keyColumn}
               AND ut_count.value > ut_user.value) as position
          FROM user_top ut_user
          INNER JOIN user u_user ON ut_user.user_id = u_user.id AND u_user.is_stats = 1
          WHERE ut_user.server_id = ?
            AND ut_user.wipe = ?
            AND u_user.steam_id = ?
        `, [cachedData.server.id, cachedData.currentWipe, cachedData.server.id, cachedData.currentWipe, currentUserSteamId]);
        
        for (const item of userPositions) {
          userTops[item.key_col] = { position: item.position };
        }
        
        // Получаем общее количество игроков в каждой категории для расчета максимальной позиции
        const totalCounts = await query<any>(`
          SELECT 
            ut.${keyColumn} as key_col,
            COUNT(*) as total_count
          FROM user_top ut
          INNER JOIN user u ON ut.user_id = u.id AND u.is_stats = 1
          WHERE ut.server_id = ?
            AND ut.wipe = ?
          GROUP BY ut.${keyColumn}
        `, [cachedData.server.id, cachedData.currentWipe]);
        
        // Для каждой категории, где пользователь не найден, устанавливаем максимальную позицию
        for (const [key] of Object.entries(TOP_TYPES)) {
          if (!userTops[key]) {
            const totalCount = totalCounts.find((tc: any) => tc.key_col === key);
            if (totalCount) {
              userTops[key] = { position: totalCount.total_count + 1 };
            }
          }
        }
        
        console.log(`[getServerStatsData] Recalculated userTops, found ${Object.keys(userTops).length} categories`);
        
        // Возвращаем кэшированные данные с обновленными userTops
        return {
          ...cachedData,
          userTops,
        };
      }
      
      console.log(`[getServerStatsData] Cache miss for ${cacheKey}, fetching data...`);

      // Получаем сервер по tag
      const serverQueryStart = Date.now();
      const [server] = await query<any>(`
        SELECT 
          id,
          tag,
          name,
          monitoring_name,
          status,
          wipe,
          next_wipe
        FROM servers
        WHERE tag = ? AND status IN (1, 2, 0)
        LIMIT 1
      `, [tag]);

      if (!server) {
        console.error(`[getServerStatsData] Server not found for tag: ${tag}`);
        return null;
      }

      // Определяем вайп
      const wipeStart = Date.now();
      let currentWipe = wipe;
      if (!currentWipe && server.wipe && server.next_wipe) {
        const wipeDate = new Date(server.wipe + ' UTC');
        const nextWipeDate = new Date(server.next_wipe + ' UTC');
        const formatDate = (date: Date) => {
          const year = date.getUTCFullYear();
          const month = String(date.getUTCMonth() + 1).padStart(2, '0');
          const day = String(date.getUTCDate()).padStart(2, '0');
          return `${year}-${month}-${day}`;
        };
        currentWipe = `${formatDate(wipeDate)}/${formatDate(nextWipeDate)}`;
      }
      
      if (!currentWipe) {
        const [lastWipe] = await query<any>(`
          SELECT DISTINCT wipe
          FROM statistics
          WHERE server_tag = ?
          ORDER BY id DESC
          LIMIT 1
        `, [tag]);
        if (lastWipe) {
          currentWipe = lastWipe.wipe;
        }
      }

      if (!currentWipe) {
        console.error(`[getServerStatsData] No wipe found for tag: ${tag}`);
        return null;
      }

      // Получаем все доступные вайпы
      const wipesQueryStart = Date.now();
      const wipes = await query<any>(`
        SELECT DISTINCT wipe
        FROM statistics
        WHERE server_tag = ?
        ORDER BY id DESC
        LIMIT 10
      `, [tag]);
      console.log(`[getServerStatsData] Wipes query took ${Date.now() - wipesQueryStart}ms, wipe determination took ${Date.now() - wipeStart}ms, currentWipe: ${currentWipe}`);

      // Получаем настройки наград за топы
      const settingsStart = Date.now();
      const settings = await getSettings();
      console.log(`[getServerStatsData] Settings query took ${Date.now() - settingsStart}ms`);

      // Один запрос для получения всех топов (включая playtime) из user_top
      const cdnUrl = process.env.CDN_URL || '';
      const keyColumn = '`key`';
      
      // Оптимизированный запрос: используем подзапрос для получения только топ-3 по каждой категории
      // Получаем все категории одним запросом, включая playtime
      const allTopsQueryStart = Date.now();
      console.log(`[getServerStatsData] Starting allTops query for server_id: ${server.id}, wipe: ${currentWipe}`);
      
      // Получаем топ-3 по всем категориям одним запросом к user_top
      const allTops = await query<any>(`
        SELECT 
          t.key_col,
          t.value,
          t.user_id,
          t.username,
          t.steam_id,
          t.last_visit_server_at,
          t.avatar,
          t.rn
        FROM (
          SELECT 
            ut.${keyColumn} as key_col,
            ut.value,
            u.id as user_id,
            u.username,
            u.steam_id,
            u.last_visit_server_at,
            up.avatar,
            ROW_NUMBER() OVER (PARTITION BY ut.${keyColumn} ORDER BY ut.value DESC) as rn
          FROM user_top ut
          INNER JOIN user u ON ut.user_id = u.id AND u.is_stats = 1
          LEFT JOIN user_profile up ON u.id = up.user_id
          WHERE ut.server_id = ?
            AND ut.wipe = ?
        ) t
        WHERE t.rn <= 3
      `, [server.id, currentWipe]);
      console.log(`[getServerStatsData] allTops query took ${Date.now() - allTopsQueryStart}ms, returned ${allTops.length} rows`);

      // Группируем топы по категориям
      const filterStart = Date.now();
      const topsByKey: Record<string, any[]> = {};
      for (const item of allTops) {
        const key = item.key_col;
        if (!topsByKey[key]) {
          topsByKey[key] = [];
        }
        topsByKey[key].push(item);
      }
      console.log(`[getServerStatsData] Grouping tops took ${Date.now() - filterStart}ms, categories found: ${Object.keys(topsByKey).length}`);

      // Вычисляем статус онлайн (за последние 10 минут)
      const now = Math.floor(Date.now() / 1000);
      const tenMinutesAgo = now - (10 * 60);

      // Формируем топы из одного запроса к user_top
      const formatTopsStart = Date.now();
      const tops: any = {};
      for (const [key, label] of Object.entries(TOP_TYPES)) {
        const topPlayers = (topsByKey[key] || [])
          .sort((a, b) => (a.rn || 999) - (b.rn || 999)) // Сортируем по позиции (rn)
          .slice(0, 3); // Берем только топ-3
        
        tops[key] = {
          label,
          items: topPlayers.map((player: any, index: number) => {
            const lastVisit = player.last_visit_server_at ? new Date(player.last_visit_server_at).getTime() / 1000 : 0;
            const isOnline = lastVisit >= tenMinutesAgo;

            return {
              position: index,
              color: getPositionColor(index),
              amount: getPositionAmount(index, settings),
              steam_id: player.steam_id,
              score: key === 'playtime' 
                ? formatPlayTime(player.value || 0)
                : Math.round(player.value || 0),
              link: `/profile/${player.steam_id}`,
              username: player.username || 'Unknown',
              avatar: formatAvatar(player.avatar, cdnUrl),
              status: isOnline,
            };
          }),
        };
      }
      console.log(`[getServerStatsData] Formatting all tops took ${Date.now() - formatTopsStart}ms`);

      // Получаем позиции текущего пользователя в каждой категории (если пользователь авторизован)
      // Используем тот же подход, что и в старой версии: подсчитываем количество игроков с большим значением + 1
      const userPositionsStart = Date.now();
      const userTops: Record<string, { position: number }> = {};
      
      if (currentUserSteamId) {
        const keyColumn = '`key`';
        console.log(`[getServerStatsData] Getting user positions for steam_id: ${currentUserSteamId}, server_id: ${server.id}, wipe: ${currentWipe}`);
        
        // Получаем позиции пользователя для всех категорий одним запросом
        // Подсчитываем количество игроков с большим значением + 1 для каждой категории
        const userPositions = await query<any>(`
          SELECT 
            ut_user.${keyColumn} as key_col,
            (SELECT COUNT(*) + 1
             FROM user_top ut_count
             INNER JOIN user u_count ON ut_count.user_id = u_count.id AND u_count.is_stats = 1
             WHERE ut_count.server_id = ?
               AND ut_count.wipe = ?
               AND ut_count.${keyColumn} = ut_user.${keyColumn}
               AND ut_count.value > ut_user.value) as position
          FROM user_top ut_user
          INNER JOIN user u_user ON ut_user.user_id = u_user.id AND u_user.is_stats = 1
          WHERE ut_user.server_id = ?
            AND ut_user.wipe = ?
            AND u_user.steam_id = ?
        `, [server.id, currentWipe, server.id, currentWipe, currentUserSteamId]);
        
        console.log(`[getServerStatsData] User positions query returned ${userPositions.length} rows:`, userPositions);
        
        for (const item of userPositions) {
          userTops[item.key_col] = { position: item.position };
        }
        
        // Получаем общее количество игроков в каждой категории для расчета максимальной позиции
        // Если пользователь не найден в категории, показываем максимальную позицию
        const totalCounts = await query<any>(`
          SELECT 
            ut.${keyColumn} as key_col,
            COUNT(*) as total_count
          FROM user_top ut
          INNER JOIN user u ON ut.user_id = u.id AND u.is_stats = 1
          WHERE ut.server_id = ?
            AND ut.wipe = ?
          GROUP BY ut.${keyColumn}
        `, [server.id, currentWipe]);
        
        // Для каждой категории, где пользователь не найден, устанавливаем максимальную позицию
        for (const [key] of Object.entries(TOP_TYPES)) {
          if (!userTops[key]) {
            const totalCount = totalCounts.find((tc: any) => tc.key_col === key);
            if (totalCount) {
              userTops[key] = { position: totalCount.total_count + 1 };
            }
          }
        }
        
        console.log(`[getServerStatsData] User positions query took ${Date.now() - userPositionsStart}ms, found ${Object.keys(userTops).length} categories:`, Object.keys(userTops));
      } else {
        console.log(`[getServerStatsData] No current user, skipping user positions query`);
      }

      // Получаем все серверы для навигации
      const allServersQueryStart = Date.now();
      const allServers = await query<any>(`
        SELECT 
          id,
          tag,
          name,
          monitoring_name,
          status
        FROM servers
        WHERE status IN (1, 2, 0)
        ORDER BY sort ASC, id ASC
      `);
      console.log(`[getServerStatsData] allServers query took ${Date.now() - allServersQueryStart}ms, returned ${allServers.length} servers`);

      const result = {
        server: {
          id: server.id,
          tag: server.tag,
          name: server.name,
          monitoring_name: server.monitoring_name,
          status: server.status,
        },
        servers: allServers.map((s: any) => ({
          id: s.id,
          tag: s.tag,
          name: s.name,
          monitoring_name: s.monitoring_name,
          status: s.status,
        })),
        tops,
        userTops, // Позиции текущего пользователя в каждой категории
        wipes: wipes.map((w: any) => w.wipe),
        currentWipe: currentWipe || '',
      };

      // Сохраняем в кэш
      const cacheSaveStart = Date.now();
      cache.set(cacheKey, result);
      cacheExpiry.set(cacheKey, Date.now() + CACHE_TTL);
      console.log(`[getServerStatsData] Cache save took ${Date.now() - cacheSaveStart}ms, cached data for ${cacheKey}, expires in ${CACHE_TTL / 1000}s`);

      const totalTime = Date.now() - startTime;
      console.log(`[getServerStatsData] END for tag: ${tag}, total time: ${totalTime}ms`);
      
      return result;
    } catch (error) {
      const totalTime = Date.now() - startTime;
      console.error(`[getServerStatsData] ERROR for tag ${tag}, total time: ${totalTime}ms:`, error);
      throw error;
    } finally {
      // Удаляем из pending requests после завершения (успешного или с ошибкой)
      pendingRequests.delete(cacheKey);
    }
  })();
  
  // Сохраняем Promise в pendingRequests
  pendingRequests.set(cacheKey, requestPromise);
  
  return requestPromise;
}
