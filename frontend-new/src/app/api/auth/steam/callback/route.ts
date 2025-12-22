import { NextResponse } from 'next/server';
import { createSteamUser, findUserBySteamId } from '@/lib/auth/steam';
import { query } from '@/lib/db';
import { cookies } from 'next/headers';

export const dynamic = 'force-dynamic';

/**
 * Обработка callback от Steam OpenID
 */
export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const mode = searchParams.get('openid.mode');

    // Определяем базовый URL из запроса (для поддержки localhost в development)
    const requestUrl = new URL(request.url);
    const protocol = requestUrl.protocol;
    const host = requestUrl.host;
    const baseUrl = `${protocol}//${host}`;

    if (mode === 'cancel') {
      // Пользователь отменил авторизацию
      return NextResponse.redirect(`${baseUrl}/?auth=cancelled`);
    }

    if (mode !== 'id_res') {
      return NextResponse.json(
        { success: false, message: 'Invalid OpenID mode' },
        { status: 400 }
      );
    }

    // Валидируем OpenID ответ
    // Получаем return_to из запроса для проверки
    const returnTo = searchParams.get('openid.return_to');
    console.log('Received return_to:', returnTo);
    console.log('Expected return_to:', `${baseUrl}/api/auth/steam/callback`);

    // Собираем параметры для валидации
    // Важно: нужно передать все параметры в том же порядке и с теми же значениями
    const validationParams: Record<string, string> = {
      'openid.ns': 'http://specs.openid.net/auth/2.0',
      'openid.mode': 'check_authentication',
    };
    
    // Копируем все openid.* параметры из запроса (кроме mode, т.к. мы его уже установили)
    searchParams.forEach((value, key) => {
      if (key.startsWith('openid.') && key !== 'openid.mode') {
        validationParams[key] = value;
      }
    });

    // Преобразуем в URLSearchParams для правильной кодировки
    const validationParamsString = new URLSearchParams(validationParams).toString();
    console.log('Validation params:', validationParamsString);

    // Валидируем через Steam (POST запрос)
    const validationUrl = 'https://steamcommunity.com/openid/login';
    const validationResponse = await fetch(validationUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: validationParamsString,
    });
    const validationText = await validationResponse.text();

    // Логируем ответ для отладки
    console.log('Steam validation response:', validationText);

    if (!validationText.includes('is_valid:true')) {
      console.error('Validation failed. Response:', validationText);
      return NextResponse.json(
        { success: false, message: 'OpenID validation failed', details: validationText },
        { status: 400 }
      );
    }

    // Извлекаем Steam ID из claimed_id
    const claimedId = searchParams.get('openid.claimed_id') || '';
    const steamIdMatch = claimedId.match(/\/id\/(\d+)$/);
    if (!steamIdMatch) {
      return NextResponse.json(
        { success: false, message: 'Invalid Steam ID format' },
        { status: 400 }
      );
    }

    const steamId = steamIdMatch[1];

    // Находим или создаем пользователя
    let user = await findUserBySteamId(steamId);
    
    if (!user) {
      // Создаем нового пользователя
      const userId = await createSteamUser(steamId, steamId);
      if (!userId) {
        return NextResponse.json(
          { success: false, message: 'Failed to create user' },
          { status: 500 }
        );
      }
      user = await findUserBySteamId(steamId);
    }

    if (!user) {
      return NextResponse.json(
        { success: false, message: 'User not found' },
        { status: 404 }
      );
    }

    // Создаем сессию (используем auth_key как токен)
    const cookieStore = await cookies();
    
    // Определяем, production ли это (по протоколу)
    const isProduction = protocol === 'https:';
    
    console.log('Setting cookie:', {
      auth_key: user.auth_key.substring(0, 10) + '...',
      isProduction,
      host,
      protocol,
    });
    
    cookieStore.set('auth_token', user.auth_key, {
      httpOnly: true,
      secure: isProduction, // true только для HTTPS
      sameSite: 'lax',
      maxAge: 60 * 60 * 24 * 7, // 7 дней
      path: '/',
    });

    // Создаем редирект с явной установкой cookie через headers
    const response = NextResponse.redirect(`${baseUrl}/`);
    
    // Убеждаемся, что cookie установлена в заголовках ответа
    response.cookies.set('auth_token', user.auth_key, {
      httpOnly: true,
      secure: isProduction,
      sameSite: 'lax',
      maxAge: 60 * 60 * 24 * 7,
      path: '/',
    });
    
    console.log('Cookie set in response headers');
    
    return response;
  } catch (error: any) {
    console.error('Error processing Steam callback:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}

