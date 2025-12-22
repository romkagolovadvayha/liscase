import React from 'react';
import HomePageClient from '@/components/homepage/HomePageClient';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';
import { formatImageUrl } from '@/lib/utils/imageUrl';
import { cookies } from 'next/headers';

async function getUserData(userId: number, steamId: string) {
  try {
    // Получаем активный сервер пользователя
    let [userServer] = await query<any>(`
      SELECT 
        s.id,
        s.tag,
        s.wipe_type,
        s.next_wipe,
        s.wipe
      FROM user u
      INNER JOIN servers s ON u.server_id = s.id
      WHERE u.id = ? AND u.status = 1 AND s.status IN (1, 2)
      LIMIT 1
    `, [userId]);

    // Если у пользователя нет активного сервера, берем первый активный сервер
    if (!userServer) {
      [userServer] = await query<any>(`
        SELECT 
          id,
          tag,
          wipe_type,
          next_wipe,
          wipe
        FROM servers
        WHERE status IN (1, 2)
        ORDER BY sort ASC, id ASC
        LIMIT 1
      `);
    }

    if (!userServer) {
      return null;
    }

    // Вычисляем текущий wipe (формат: YYYY-MM-DD/YYYY-MM-DD)
    // Если wipe или next_wipe NULL, используем текущую дату
    const now = new Date();
    const wipeDate = userServer.wipe 
      ? new Date(userServer.wipe).toISOString().split('T')[0] 
      : now.toISOString().split('T')[0];
    const nextWipeDate = userServer.next_wipe 
      ? new Date(userServer.next_wipe).toISOString().split('T')[0] 
      : now.toISOString().split('T')[0];
    const currentWipe = `${wipeDate}/${nextWipeDate}`;

    // Получаем статистику пользователя
    const userStatsRaw = await query<any>(`
      SELECT 
        \`key\`,
        value
      FROM statistics
      WHERE steam_id = ? AND server_tag = ? AND wipe = ?
    `, [steamId, userServer.tag, currentWipe]);

    // Преобразуем в объект
    const userStats: Record<string, number> = {};
    userStatsRaw.forEach((stat: any) => {
      userStats[stat.key] = stat.value || 0;
    });

    // Вычисляем K/D
    const kills = userStats['kills'] || 0;
    const deaths = userStats['deaths'] || 0;
    userStats['kd'] = deaths > 0 ? kills / deaths : 0;

    // Получаем активный VIP
    const [activeVip] = await query<any>(`
      SELECT 
        expires_at
      FROM user_vip
      WHERE user_id = ? AND expires_at > NOW()
      ORDER BY expires_at DESC
      LIMIT 1
    `, [userId]);

    // Получаем награды (tasks_v2) - оборачиваем в try-catch, чтобы не ломать весь запрос
    let tasksV2: any[] = [];
    let userCompletions: any[] = [];
    let awards: any[] = [];
    let awardsStats = { completed: 0, total: 0 };

    try {
      tasksV2 = await query<any>(`
        SELECT 
          t.id,
          t.title,
          t.image_path,
          t.sort
        FROM tasks_v2 t
        WHERE t.is_active = 1
        ORDER BY t.sort ASC
      `);

      // Получаем выполненные задания пользователя (таблица называется task_v2_user_completion, без 's')
      userCompletions = await query<any>(`
        SELECT 
          task_id,
          count_completed
        FROM task_v2_user_completion
        WHERE user_id = ? AND count_completed > 0
      `, [userId]);

      const completionsMap = new Map(userCompletions.map((c: any) => [c.task_id, c.count_completed]));

      // Формируем массив наград (только выполненные, максимум 7)
      awards = tasksV2
        .filter((task: any) => completionsMap.has(task.id))
        .slice(0, 7)
        .map((task: any) => ({
          id: task.id,
          name: task.title,
          image: task.image_path && task.image_path.startsWith('/') 
            ? task.image_path 
            : `/${task.image_path}`,
          completed: true,
        }));

      awardsStats = {
        completed: userCompletions.length,
        total: tasksV2.length,
      };
    } catch (error) {
      // Если таблица не существует, просто пропускаем награды
      console.warn('Tasks tables not found, skipping awards:', error);
    }

    // Получаем ссылку на статистику пользователя
    const userStatsLink = `/profile/${steamId}`;

    return {
      userStats,
      activeVip: activeVip ? {
        expires_at: activeVip.expires_at,
      } : null,
      activeVipTimestamp: activeVip ? Math.floor(new Date(activeVip.expires_at).getTime() / 1000) : null,
      awards,
      awardsStats,
      userStatsLink,
      serverActiveTag: userServer.tag,
    };
  } catch (error) {
    console.error('Error fetching user data:', error);
    return null;
  }
}

async function getHomePageData() {
  try {
    // Получаем текущего пользователя
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;
    
    let userData = null;
    if (authToken) {
      const [user] = await query<{ id: number; steam_id: string; username: string }>(`
        SELECT id, steam_id, username
        FROM user
        WHERE auth_key = ? AND status = 1
        LIMIT 1
      `, [authToken]);

      if (user) {
        const statsData = await getUserData(user.id, user.steam_id);
        if (statsData) {
          userData = {
            ...statsData,
            username: user.username,
          };
        }
      }
    }

    // Получаем настройки
    const settings = await getSettings();

    // Получаем категории
    const categoriesRaw = await query<any>(`
      SELECT 
        id,
        name,
        image,
        sort
      FROM category
      ORDER BY sort ASC, name ASC
    `);

    // Получаем CDN URL из настроек (только для продуктов)
    const cdnUrl = (settings.site_cdnUrl as string) || '';

    // Категории без CDN - используем изображения как есть
    const categories = categoriesRaw.map((category: any) => ({
      ...category,
      image: category.image || undefined,
    }));

    // Получаем товары для главной страницы
    // TYPE_150 = 4 (см. common/models/box/DropImage.php)
    const productsRaw = await query<any>(`
      SELECT 
        d.id,
        d.name,
        d.eng_name,
        d.price,
        d.count,
        d.discount,
        d.category_id,
        d.status,
        d.drop_type,
        d.blocked_hour,
        d.blocked_at,
        di.image,
        c.name as category_name
      FROM \`drop\` d
      LEFT JOIN drop_image di ON d.id = di.drop_id AND di.type = 4
      LEFT JOIN category c ON d.category_id = c.id
      WHERE d.status = 1 AND d.market_status = 1
      ORDER BY d.sort ASC, d.id DESC
      LIMIT 20
    `);

    // Форматируем URL изображений с учетом CDN (используем уже объявленный cdnUrl)
    // Форматируем только если изображение есть
    const products = productsRaw.map((product: any) => ({
      ...product,
      image: product.image ? formatImageUrl(product.image, cdnUrl) : null,
    }));

    // Получаем статистику проекта
    const [onlineStats] = await query<{ total_online: number; total_users: number; total_servers: number }>(`
      SELECT 
        COALESCE(SUM(CASE WHEN status = 1 THEN players ELSE 0 END), 0) as total_online,
        (SELECT COUNT(*) FROM user WHERE status = 1) as total_users,
        (SELECT COUNT(*) FROM servers WHERE status IN (1, 2)) as total_servers
      FROM servers
    `);

    // Получаем первый активный сервер для неавторизованных пользователей
    const [firstServer] = await query<{ tag: string }>(`
      SELECT tag
      FROM servers
      WHERE status IN (1, 2)
      ORDER BY sort ASC, id ASC
      LIMIT 1
    `);

    return {
      categories: categories || [],
      products: products || [],
      projectStats: {
        online: onlineStats?.total_online || 0,
        users: onlineStats?.total_users || 0,
        count: onlineStats?.total_servers || 0,
      },
      userData: userData,
      serverActiveTag: firstServer?.tag || null,
      settings: {
        botLink: settings.tgbot_login ? `https://t.me/${settings.tgbot_login}` : '#',
        bonusImage: (settings.design_bonusBlockImage as string) || '',
        bonusImageVideo: (settings.design_bonusBlockImageVideo as string) || '',
        statsImage: (settings.design_statsBlockImage as string) || '',
        statsImageVideo: (settings.design_statsBlockImageVideo as string) || '',
        notAuthImage: (settings.design_image_not_auth as string) || '',
      },
    };
  } catch (error) {
    console.error('Error fetching homepage data:', error);
    return {
      categories: [],
      products: [],
      projectStats: {
        online: 0,
        users: 0,
        count: 0,
      },
      settings: {
        botLink: '#',
        bonusImage: '',
        bonusImageVideo: '',
        statsImage: '',
        statsImageVideo: '',
        notAuthImage: '',
      },
    };
  }
}

export default async function HomePage() {
  const data = await getHomePageData();

  return <HomePageClient initialData={data} />;
}
