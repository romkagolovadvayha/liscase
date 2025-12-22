import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';
import { getServerStatsData } from '@/lib/server-stats';

// Кэшируем на час (3600 секунд)
export const revalidate = 3600;

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ tag: string }> }
) {
  const apiStartTime = Date.now();
  console.log(`[API /api/servers/[tag]/stats] START`);
  
  try {
    const paramsStart = Date.now();
    const { tag } = await params;
    const searchParams = request.nextUrl.searchParams;
    const wipeParam = searchParams.get('wipe');
    console.log(`[API /api/servers/[tag]/stats] Params parsing took ${Date.now() - paramsStart}ms, tag: ${tag}, wipe: ${wipeParam || 'none'}`);

    // Получаем текущего пользователя (если авторизован)
    let currentUserSteamId: string | undefined = undefined;
    try {
      const cookieStore = await cookies();
      const authToken = cookieStore.get('auth_token')?.value;
      
      if (authToken) {
        const [user] = await query<{ steam_id: string }>(`
          SELECT steam_id FROM user WHERE auth_key = ? AND status = 1 LIMIT 1
        `, [authToken]);
        if (user) {
          currentUserSteamId = user.steam_id;
        }
      }
    } catch (error) {
      console.error(`[API /api/servers/[tag]/stats] Error getting current user:`, error);
      // Продолжаем без информации о пользователе
    }

    const dataFetchStart = Date.now();
    const data = await getServerStatsData(tag, wipeParam || undefined, currentUserSteamId);
    console.log(`[API /api/servers/[tag]/stats] getServerStatsData took ${Date.now() - dataFetchStart}ms`);

    if (!data) {
      console.log(`[API /api/servers/[tag]/stats] Server not found, total time: ${Date.now() - apiStartTime}ms`);
      return NextResponse.json(
        { success: false, message: 'Сервер не найден' },
        { status: 404 }
      );
    }

    const responseStart = Date.now();
    const response = NextResponse.json({
      success: true,
      data,
    });

    // Добавляем Cache-Control заголовки для клиентского кэширования
    response.headers.set(
      'Cache-Control',
      'public, s-maxage=3600, stale-while-revalidate=7200'
    );
    console.log(`[API /api/servers/[tag]/stats] Response creation took ${Date.now() - responseStart}ms, total API time: ${Date.now() - apiStartTime}ms`);

    return response;
  } catch (error: any) {
    console.error(`[API /api/servers/[tag]/stats] Error, total time: ${Date.now() - apiStartTime}ms:`, error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при загрузке статистики' },
      { status: 500 }
    );
  }
}
