import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';

// API endpoint для получения токена для WebSocket
// Токен используется только для авторизации WebSocket и не содержит чувствительных данных
export async function GET() {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json(
        { success: false, message: 'Unauthorized' },
        { status: 401 }
      );
    }

    // Проверяем, что токен валидный
    const [user] = await query<any>(`
      SELECT id, steam_id
      FROM user
      WHERE auth_key = ? AND status = 1
      LIMIT 1
    `, [authToken]);

    if (!user) {
      return NextResponse.json(
        { success: false, message: 'Invalid token' },
        { status: 401 }
      );
    }

    // Возвращаем токен и steam_id для WebSocket авторизации
    return NextResponse.json({
      success: true,
      token: authToken,
      steam_id: user.steam_id,
    });
  } catch (error: any) {
    console.error('Error getting WS token:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}









