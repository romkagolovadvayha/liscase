import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';

export const dynamic = 'force-dynamic';

/**
 * Получение статистики реферальной системы (для вкладки "Условия программы")
 */
export async function GET(request: NextRequest) {
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

    // Получаем статистику параллельно (с правильным пулом соединений это безопасно)
    const [profileResult, registeredResult, playedResult, depositsResult, payoutResult] = await Promise.all([
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
      `, [user.id])
    ]);

    const profile = profileResult[0];
    const registeredCount = registeredResult[0];
    const playedCount = playedResult[0];
    const deposits = depositsResult[0];
    const payoutSum = payoutResult[0];

    const referralPercent = profile?.referral_bonus || 0;
    const totalDeposits = deposits?.total || 0;
    const payoutSumValue = payoutSum?.sum || 0;
    const referralBalance = Math.max(0, (totalDeposits * referralPercent / 100) - payoutSumValue);

    return NextResponse.json({
      success: true,
      stats: {
        partnerLink,
        referralPercent: referralPercent,
        referralClicks: profile?.referral_click || 0,
        registeredCount: registeredCount?.count || 0,
        playedCount: playedCount?.count || 0,
        referralBalance: Math.ceil(referralBalance),
      },
    });
  } catch (error: any) {
    console.error('Error fetching referral stats:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}

