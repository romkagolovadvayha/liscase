import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query, execute } from '@/lib/db';

/**
 * Получение промокода пользователя
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
      promocode: string | null;
    }>(`
      SELECT id, promocode
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

    return NextResponse.json({
      success: true,
      promocode: user.promocode || null,
    });
  } catch (error: any) {
    console.error('Error fetching promocode:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}

/**
 * Создание/обновление промокода пользователя
 */
export async function POST(request: NextRequest) {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        message: 'Не авторизован',
      }, { status: 401 });
    }

    const body = await request.json();
    const { promocode } = body;

    if (!promocode || typeof promocode !== 'string') {
      return NextResponse.json({
        success: false,
        message: 'Промокод не указан',
      }, { status: 400 });
    }

    const trimmedPromocode = promocode.trim();

    // Валидация длины
    if (trimmedPromocode.length < 5 || trimmedPromocode.length > 120) {
      return NextResponse.json({
        success: false,
        message: 'Промокод должен быть от 5 до 120 символов',
      }, { status: 400 });
    }

    // Валидация формата (только латинские буквы, цифры и дефис)
    if (!/^[a-zA-Z0-9-]+$/.test(trimmedPromocode)) {
      return NextResponse.json({
        success: false,
        message: 'Разрешены только буквы латинского алфавита, цифры и дефис',
      }, { status: 400 });
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
        message: 'Пользователь не найден',
      }, { status: 401 });
    }

    // Проверяем, не используется ли промокод другим пользователем
    const [existingUser] = await query<{
      id: number;
    }>(`
      SELECT id
      FROM user
      WHERE BINARY promocode = ? AND id != ? AND promocode IS NOT NULL AND promocode != ''
      LIMIT 1
    `, [trimmedPromocode, user.id]);

    if (existingUser) {
      return NextResponse.json({
        success: false,
        message: 'Промокод уже существует, используйте другой',
      }, { status: 400 });
    }

    // Проверяем, не существует ли промокод в таблице promocode
    const [existingPromocode] = await query<{
      id: number;
    }>(`
      SELECT id
      FROM promocode
      WHERE BINARY code = ?
      LIMIT 1
    `, [trimmedPromocode]);

    if (existingPromocode) {
      return NextResponse.json({
        success: false,
        message: 'Промокод уже существует, используйте другой',
      }, { status: 400 });
    }

    // Обновляем промокод пользователя
    await execute(`
      UPDATE user
      SET promocode = ?
      WHERE id = ?
    `, [trimmedPromocode, user.id]);

    return NextResponse.json({
      success: true,
      message: 'Промокод успешно создан',
      promocode: trimmedPromocode,
    });
  } catch (error: any) {
    console.error('Error creating promocode:', error);
    return NextResponse.json({
      success: false,
      message: error.message || 'Ошибка при создании промокода',
    }, { status: 500 });
  }
}






