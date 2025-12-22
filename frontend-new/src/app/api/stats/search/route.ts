import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const q = searchParams.get('q');
    const serverId = searchParams.get('serverId');

    if (!q || !serverId) {
      return NextResponse.json({ success: false, message: 'Missing parameters' }, { status: 400 });
    }

    // Получаем пользователей для сервера за последние 30 дней
    const date = new Date();
    date.setDate(date.getDate() - 30);
    const dateStr = date.toISOString().slice(0, 19).replace('T', ' ');

    const needle = q.toLowerCase();
    
    // Получаем всех активных пользователей сервера с is_stats = 1 за последние 30 дней
    const users = await query<any>(`
      SELECT 
        u.id,
        u.username,
        u.steam_id,
        u.last_visit_server_at,
        up.avatar
      FROM user u
      LEFT JOIN user_profile up ON u.id = up.user_id
      WHERE u.server_id = ?
        AND u.status = 1
        AND u.is_stats = 1
        AND u.last_visit_server_at >= ?
      ORDER BY u.last_visit_server_at DESC
      LIMIT 1000
    `, [serverId, dateStr]);

    // Фильтруем по поисковому запросу
    const filtered = users.filter((user: any) => {
      const usernameLower = (user.username || '').toLowerCase();
      const steamId = String(user.steam_id || '');
      return usernameLower.includes(needle) || steamId.includes(needle);
    }).slice(0, 15); // Берем первые 15 результатов

    // Форматируем результаты
    const cdnUrl = process.env.CDN_URL || '';
    const defaultAvatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';
    
    // Вычисляем статус онлайн (за последние 10 минут)
    const now = Math.floor(Date.now() / 1000);
    const tenMinutesAgo = now - (10 * 60);
    
    const items = filtered.map((user: any) => {
      // Форматируем аватар как в Header (используя CDN_URL)
      let avatar = '';
      if (user.avatar && user.avatar.trim()) {
        const avatarPath = user.avatar.trim();
        if (avatarPath.startsWith('http://') || avatarPath.startsWith('https://')) {
          avatar = avatarPath;
        } else {
          // Используем CDN_URL напрямую, как в Header
          const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
          avatar = `${baseUrl}${avatarPath.startsWith('/') ? avatarPath : '/' + avatarPath}`;
        }
      }
      if (!avatar) {
        const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
        avatar = `${baseUrl}${defaultAvatar}`;
      }

      // Определяем статус онлайн
      const lastVisit = user.last_visit_server_at ? new Date(user.last_visit_server_at).getTime() / 1000 : 0;
      const isOnline = lastVisit >= tenMinutesAgo;

      return {
        id: user.id,
        name: user.username,
        steam_id: user.steam_id,
        avatar,
        status: isOnline, // true = онлайн, false = оффлайн
      };
    });

    // Получаем tag сервера для формирования ссылок
    const [server] = await query<{ tag: string }>(`
      SELECT tag FROM servers WHERE id = ? LIMIT 1
    `, [serverId]);

    const result = items.map((item: any) => ({
      ...item,
      statsLink: `/profile/${item.steam_id}`,
    }));

    return NextResponse.json({
      success: true,
      items: result,
    });
  } catch (error: any) {
    console.error('Error in stats search:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при поиске' },
      { status: 500 }
    );
  }
}

