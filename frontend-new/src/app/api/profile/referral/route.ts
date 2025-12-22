import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';

/**
 * Получение данных реферальной системы
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
      username: string;
      ref_code: string;
    }>(`
      SELECT id, username, ref_code
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

    // Получаем настройки
    const settings = await getSettings();
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000';
    const partnerLink = `${baseUrl}/p/${user.ref_code}`;

    // Получаем профиль и статистику параллельно для оптимизации
    const [profileResult, registeredResult, playedResult, depositsResult, payoutResult, totalResult] = await Promise.all([
      query<{
        referral_bonus: number;
        referral_click: number;
      }>(`
        SELECT COALESCE(referral_bonus, 0) as referral_bonus, COALESCE(referral_click, 0) as referral_click
        FROM user_profile
        WHERE user_id = ?
        LIMIT 1
      `, [user.id]),
      query<{
        count: number;
      }>(`
        SELECT COUNT(*) as count
        FROM user_tree
        WHERE parent_user_id = ? AND user_id != ?
      `, [user.id, user.id]),
      query<{
        count: number;
      }>(`
        SELECT COUNT(*) as count
        FROM user_tree ut
        INNER JOIN user_profile up ON ut.user_id = up.user_id
        WHERE ut.parent_user_id = ? AND ut.user_id != ? AND up.parent_bonus = 1
      `, [user.id, user.id]),
      query<{
        total: number;
      }>(`
        SELECT COALESCE(SUM(d.amount), 0) as total
        FROM deposit d
        INNER JOIN user_tree ut ON d.user_id = ut.user_id
        WHERE ut.parent_user_id = ? AND ut.user_id != ? AND d.status = 2
      `, [user.id, user.id]),
      query<{
        sum: number;
      }>(`
        SELECT COALESCE(SUM(amount), 0) as sum
        FROM user_payout_referral
        WHERE user_id = ?
      `, [user.id]),
      query<{
        count: number;
      }>(`
        SELECT COUNT(*) as count
        FROM user_tree ut
        INNER JOIN user u ON ut.user_id = u.id
        WHERE ut.parent_user_id = ? AND ut.user_id != ?
      `, [user.id, user.id])
    ]);

    const profile = profileResult[0];
    const registeredCount = registeredResult[0];
    const playedCount = playedResult[0];
    const deposits = depositsResult[0];
    const payoutSum = payoutResult[0];
    const totalCount = totalResult[0];

    const referralPercent = profile?.referral_bonus || 0;
    const totalDeposits = deposits?.total || 0;
    const payoutSumValue = payoutSum?.sum || 0;
    const referralBalance = Math.max(0, (totalDeposits * referralPercent / 100) - payoutSumValue);
    const total = totalCount?.count || 0;

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
    `, [user.id, user.id, pageSize, offset]);

    return NextResponse.json({
      success: true,
      referral: {
        partnerLink,
        referralPercent: referralPercent,
        referralClicks: profile?.referral_click || 0,
        registeredCount: registeredCount?.count || 0,
        playedCount: playedCount?.count || 0,
        referralBalance: Math.ceil(referralBalance),
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
      },
    });
  } catch (error: any) {
    console.error('Error fetching referral data:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}

