import { NextResponse } from 'next/server';

export const dynamic = 'force-dynamic';

/**
 * Начало авторизации через Steam
 * Редиректит на Steam OpenID
 */
export async function GET(request: Request) {
  try {
    // Определяем базовый URL из запроса (для поддержки localhost в development)
    const requestUrl = new URL(request.url);
    const protocol = requestUrl.protocol;
    const host = requestUrl.host;
    const baseUrl = `${protocol}//${host}`;
    const returnTo = `${baseUrl}/api/auth/steam/callback`;
    
    console.log('Steam auth init - baseUrl:', baseUrl);
    console.log('Steam auth init - returnTo:', returnTo);
    
    // Steam OpenID endpoint
    const steamOpenIdUrl = 'https://steamcommunity.com/openid/login';
    
    // Параметры для OpenID запроса
    const params = new URLSearchParams({
      'openid.ns': 'http://specs.openid.net/auth/2.0',
      'openid.mode': 'checkid_setup',
      'openid.return_to': returnTo,
      'openid.realm': baseUrl,
      'openid.identity': 'http://specs.openid.net/auth/2.0/identifier_select',
      'openid.claimed_id': 'http://specs.openid.net/auth/2.0/identifier_select',
    });

    // Редиректим на Steam
    return NextResponse.redirect(`${steamOpenIdUrl}?${params.toString()}`);
  } catch (error: any) {
    console.error('Error initiating Steam auth:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}

