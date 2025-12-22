import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';

export const dynamic = 'force-dynamic';

/**
 * Получение информации о текущем авторизованном пользователе
 */
export async function GET() {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        isGuest: true,
      });
    }

    // Ищем пользователя по auth_key
    const [user] = await query<{
      id: number;
      username: string;
      steam_id: string;
      avatar: string | null;
      server_id: number | null;
      server_tag: string | null;
    }>(`
      SELECT 
        u.id,
        u.username,
        u.steam_id,
        up.avatar,
        s.id as server_id,
        s.tag as server_tag
      FROM user u
      LEFT JOIN user_profile up ON u.id = up.user_id
      LEFT JOIN servers s ON u.server_id = s.id
      WHERE u.auth_key = ? AND u.status = 1
      LIMIT 1
    `, [authToken]);

    if (!user) {
      return NextResponse.json({
        success: false,
        isGuest: true,
      });
    }

    // Получаем баланс из user_balance (TYPE_PERSONAL = 1)
    const [balanceData] = await query<{
      balance: number;
    }>(`
      SELECT balance
      FROM user_balance
      WHERE user_id = ? AND type = 1
      LIMIT 1
    `, [user.id]);

    const balance = balanceData?.balance || 0;

    // Форматируем аватар
    const avatar = user.avatar || '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';

    // Если у пользователя нет сервера, берем первый активный/выключенный
    let serverTag = user.server_tag;
    if (!serverTag) {
      const [firstServer] = await query<{ tag: string }>(`
        SELECT tag
        FROM servers
        WHERE status IN (1, 0)
        ORDER BY sort ASC, id ASC
        LIMIT 1
      `);
      if (firstServer) {
        serverTag = firstServer.tag;
      }
    }

    return NextResponse.json({
      success: true,
      isGuest: false,
      data: {
        id: user.id,
        username: user.username,
        steam_id: user.steam_id,
        balance: balance,
        avatar: avatar,
        server_id: user.server_id,
        server_tag: serverTag,
      },
    });
  } catch (error: any) {
    console.error('Error fetching user:', error);
    return NextResponse.json({
      success: false,
      isGuest: true,
      message: error.message,
    });
  }
}





