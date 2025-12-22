import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';
import { formatImageUrl } from '@/lib/utils/imageUrl';

export const dynamic = 'force-dynamic';

interface BanListParams {
  page?: number;
  pageSize?: number;
  steam_id?: string;
  reason?: string;
  server_id?: number;
  banned_at?: string;
  unbanned_at?: string;
}

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const page = parseInt(searchParams.get('page') || '1', 10);
    const pageSize = parseInt(searchParams.get('pageSize') || '20', 10);
    const steamId = searchParams.get('steam_id') || '';
    const reason = searchParams.get('reason') || '';
    const serverId = searchParams.get('server_id');
    const bannedAt = searchParams.get('banned_at') || '';
    const unbannedAt = searchParams.get('unbanned_at') || '';
    const sortField = searchParams.get('sort') || 'banned_at';
    const sortOrder = searchParams.get('order') || 'desc';

    // Получаем настройки для CDN URL
    const settings = await getSettings();
    const cdnUrl = (settings.site_cdnUrl as string) || '';
    const defaultAvatar = (settings.design_avatar_default as string) || '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';

    // Формируем условия WHERE
    const whereConditions: string[] = [];
    const params: any[] = [];

    // Фильтр по steam_id (поиск по username или steam_id)
    if (steamId) {
      whereConditions.push(`(b.username LIKE ? OR b.steam_id LIKE ?)`);
      const searchTerm = `%${steamId}%`;
      params.push(searchTerm, searchTerm);
    }

    // Фильтр по reason
    if (reason) {
      whereConditions.push(`b.reason LIKE ?`);
      params.push(`%${reason}%`);
    }

    // Фильтр по server_id
    if (serverId) {
      whereConditions.push(`b.server_id = ?`);
      params.push(parseInt(serverId, 10));
    }

    // Фильтр по banned_at (дата начала бана)
    if (bannedAt) {
      whereConditions.push(`DATE(b.banned_at) = ?`);
      params.push(bannedAt);
    }

    // Фильтр по unbanned_at (дата разбана)
    if (unbannedAt) {
      whereConditions.push(`DATE(b.unbanned_at) = ?`);
      params.push(unbannedAt);
    }

    // Показываем только активные баны (unbanned_at в будущем или NULL)
    whereConditions.push(`(b.unbanned_at IS NULL OR b.unbanned_at >= NOW())`);

    const whereClause = whereConditions.length > 0 
      ? `WHERE ${whereConditions.join(' AND ')}`
      : '';

    // Получаем общее количество записей
    const [countResult] = await query<{ count: number }>(`
      SELECT COUNT(*) as count
      FROM bans b
      ${whereClause}
    `, params);

    const total = countResult?.count || 0;

    // Вычисляем offset
    const offset = (page - 1) * pageSize;

    // Определяем поле для сортировки и направление
    const validSortFields: Record<string, string> = {
      'username': 'b.username',
      'server': 's.monitoring_name',
      'first_seen': 'u.created_at',
      'banned_at': 'b.banned_at',
      'reason': 'b.reason',
    };

    const sortFieldSql = validSortFields[sortField] || 'b.banned_at';
    const sortOrderSql = sortOrder === 'asc' ? 'ASC' : 'DESC';

    // Получаем данные с пагинацией
    // Создаем новый массив параметров для запроса с LIMIT и OFFSET
    // Убеждаемся, что все параметры правильно типизированы
    const limitValue = parseInt(String(pageSize), 10);
    const offsetValue = parseInt(String(offset), 10);
    const queryParams = [...params, limitValue, offsetValue];
    
    const bans = await query<any>(`
      SELECT 
        b.id,
        b.username,
        b.user_id,
        b.steam_id,
        b.reason,
        b.banned_at,
        b.unbanned_at,
        b.server_id,
        s.monitoring_name as server_name,
        s.tag as server_tag,
        up.avatar,
        u.created_at as first_seen
      FROM bans b
      LEFT JOIN servers s ON b.server_id = s.id
      LEFT JOIN user_profile up ON b.user_id = up.user_id
      LEFT JOIN user u ON b.user_id = u.id
      ${whereClause}
      ORDER BY ${sortFieldSql} ${sortOrderSql}
      LIMIT ? OFFSET ?
    `, queryParams);

    // Форматируем данные
    const formattedBans = bans.map((ban: any) => {
      // Формируем URL аватара
      let avatarUrl = defaultAvatar;
      if (ban.avatar) {
        avatarUrl = ban.avatar.startsWith('/') 
          ? formatImageUrl(ban.avatar, cdnUrl)
          : formatImageUrl(`/${ban.avatar}`, cdnUrl);
      } else {
        avatarUrl = formatImageUrl(defaultAvatar, cdnUrl);
      }

      return {
        id: ban.id,
        username: ban.username || ban.steam_id,
        steam_id: ban.steam_id,
        avatar: avatarUrl,
        reason: ban.reason || 'Причина не указана',
        banned_at: ban.banned_at,
        unbanned_at: ban.unbanned_at,
        server_id: ban.server_id,
        server_name: ban.server_name || 'Все сервера',
        server_tag: ban.server_tag,
        first_seen: ban.first_seen,
      };
    });

    return NextResponse.json({
      success: true,
      data: formattedBans,
      pagination: {
        page,
        pageSize,
        total,
        totalPages: Math.ceil(total / pageSize),
      },
    });
  } catch (error: any) {
    console.error('Error fetching banlist:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}

