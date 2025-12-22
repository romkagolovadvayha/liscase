import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query, execute } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';

export const dynamic = 'force-dynamic';

/**
 * Получение бонуса за приглашенного пользователя
 */
export async function POST(request: Request) {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        message: 'Не авторизован',
      }, { status: 401 });
    }

    // Получаем текущего пользователя
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

    const { searchParams } = new URL(request.url);
    const childUserId = searchParams.get('id');

    if (!childUserId) {
      return NextResponse.json({
        success: false,
        message: 'ID пользователя не указан',
      }, { status: 400 });
    }

    // Проверяем, что пользователь является родителем
    const [userTree] = await query<{
      user_id: number;
    }>(`
      SELECT user_id
      FROM user_tree
      WHERE parent_user_id = ? AND user_id = ?
      LIMIT 1
    `, [user.id, childUserId]);

    if (!userTree) {
      return NextResponse.json({
        success: false,
        message: 'Вы не приглашали данного игрока!',
      }, { status: 403 });
    }

    // Получаем данные приглашенного пользователя
    const [childUser] = await query<{
      id: number;
      username: string;
      parent_skin_send: number;
    }>(`
      SELECT id, username, parent_skin_send
      FROM user
      WHERE id = ?
      LIMIT 1
    `, [childUserId]);

    if (!childUser) {
      return NextResponse.json({
        success: false,
        message: 'Пользователь не найден',
      }, { status: 404 });
    }

    // Получаем профиль приглашенного пользователя
    const [childProfile] = await query<{
      parent_bonus: number;
    }>(`
      SELECT parent_bonus
      FROM user_profile
      WHERE user_id = ?
      LIMIT 1
    `, [childUserId]);

    // Проверяем, что награда еще не получена
    if (childUser.parent_skin_send && childProfile?.parent_bonus) {
      return NextResponse.json({
        success: false,
        message: 'Награда уже получена!',
      }, { status: 400 });
    }

    // TODO: Проверка hasHourInServer - нужно реализовать проверку времени на сервере
    // Пока пропускаем эту проверку

    // Получаем настройки
    const settings = await getSettings();
    const referralBonus = settings.referral_bonus || 0;

    // Начисляем бонус
    if (!childProfile?.parent_bonus) {
      await execute(`
        UPDATE user_profile
        SET parent_bonus = 1
        WHERE user_id = ?
      `, [childUserId]);

      // Получаем баланс пользователя
      const [balance] = await query<{
        id: number;
      }>(`
        SELECT id
        FROM user_balance
        WHERE user_id = ? AND type = 1
        LIMIT 1
      `, [user.id]);

      if (!balance) {
        // Создаем баланс если его нет
        await execute(`
          INSERT INTO user_balance (user_id, type, balance, created_at)
          VALUES (?, 1, 0, NOW())
        `, [user.id]);
      }

      // Создаем profit
      await execute(`
        INSERT INTO profit (status, type, amount, user_balance_id, comment, created_at)
        VALUES (1, 3, ?, ?, ?, NOW())
      `, [
        referralBonus,
        balance?.id || null,
        `Бонус за приглашенного пользователя "${childUser.username}"`
      ]);

      // Пересчитываем баланс
      // TODO: Реализовать пересчет баланса
    }

    // Отмечаем, что скин отправлен
    if (!childUser.parent_skin_send) {
      await execute(`
        UPDATE user
        SET parent_skin_send = 1
        WHERE id = ?
      `, [childUserId]);

      // TODO: Отправка скина через RustTM API
    }

    return NextResponse.json({
      success: true,
      message: 'Награда успешно получена!',
    });
  } catch (error: any) {
    console.error('Error getting referral bonus:', error);
    return NextResponse.json({
      success: false,
      message: error.message || 'Ошибка при получении награды',
    }, { status: 500 });
  }
}






