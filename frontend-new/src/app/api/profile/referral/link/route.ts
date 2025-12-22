import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';

/**
 * Получение партнерской ссылки (для вкладки "Как приглашать?")
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
      ref_code: string;
    }>(`
      SELECT id, ref_code
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

    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000';
    const partnerLink = `${baseUrl}/p/${user.ref_code}`;

    return NextResponse.json({
      success: true,
      partnerLink,
    });
  } catch (error: any) {
    console.error('Error fetching referral link:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}






