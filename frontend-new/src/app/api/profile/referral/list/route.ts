import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';

export const dynamic = 'force-dynamic';

/**
 * Получение списка рефералов (для вкладки "Мои рефералы")
 */
export async function GET(request: NextRequest) {
  const searchParams = request.nextUrl.searchParams;
  const page = parseInt(searchParams.get('page') || '1', 10);
  const pageSize = parseInt(searchParams.get('pageSize') || '10', 10);
  
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        isGuest: true,
      }, { status: 401 });
    }

    // Получаем пользователя
    const [user] = await query<{
      id: number;
    }>(`
      SELECT id
      FROM user
      WHERE auth_key = ? AND status = 1
      LIMIT 1
    `, [authToken]);

    if (!user) {
      return NextResponse.json({
        success: false,
        isGuest: true,
      }, { status: 401 });
    }

    // Получаем общее количество рефералов
    const [totalResult] = await query<{
      count: number;
    }>(`
      SELECT COUNT(*) as count
      FROM user_tree ut
      INNER JOIN user u ON ut.user_id = u.id
      WHERE ut.parent_user_id = ? AND ut.user_id != ?
    `, [user.id, user.id]);

    const total = totalResult?.count || 0;
    const totalPages = Math.ceil(total / pageSize);
    const offset = (page - 1) * pageSize;

    // Получаем параметры сортировки
    const sortField = searchParams.get('sort') || 'createdAt';
    const sortOrder = searchParams.get('order') || 'desc';

    // Определяем поле для сортировки и направление
    const validSortFields: Record<string, string> = {
      'username': 'u.username',
      'createdAt': 'u.created_at',
      'hasBonus': 'up.parent_bonus',
    };

    const sortFieldSql = validSortFields[sortField] || 'u.created_at';
    const sortOrderSql = sortOrder === 'asc' ? 'ASC' : 'DESC';

    // Получаем список приглашенных с пагинацией
    const referrals = await query<{
      user_id: number;
      username: string;
      avatar: string | null;
      created_at: string;
      parent_bonus: number;
      parent_skin_send: number;
    }>(`
      SELECT 
        u.id as user_id,
        u.username,
        up.avatar,
        u.created_at,
        up.parent_bonus,
        u.parent_skin_send
      FROM user_tree ut
      INNER JOIN user u ON ut.user_id = u.id
      LEFT JOIN user_profile up ON u.id = up.user_id
      WHERE ut.parent_user_id = ? AND ut.user_id != ?
      ORDER BY ${sortFieldSql} ${sortOrderSql}
      LIMIT ? OFFSET ?
    `, [user.id, user.id, parseInt(String(pageSize), 10), parseInt(String(offset), 10)]);

    return NextResponse.json({
      success: true,
      referrals: referrals.map(r => ({
        userId: r.user_id,
        username: r.username,
        avatar: r.avatar || '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png',
        createdAt: r.created_at,
        hasBonus: r.parent_bonus === 1,
        hasSkinSent: r.parent_skin_send === 1,
      })),
      pagination: {
        page,
        pageSize,
        totalPages,
        total,
      },
    });
  } catch (error: any) {
    console.error('Error fetching referral list:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}






