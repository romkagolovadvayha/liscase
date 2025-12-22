import { NextResponse } from 'next/server';
import { getDbConnection, query } from '@/lib/db';
import { cookies } from 'next/headers';

export const dynamic = 'force-dynamic';

const TYPE_SKINS = 2; // Тип баланса для скинов

export async function POST(
  request: Request,
  { params }: { params: { id: string } }
) {
  const conn = await getDbConnection();
  
  try {
    const skinId = parseInt(params.id);
    
    if (isNaN(skinId)) {
      return NextResponse.json(
        { success: false, message: 'Неверный ID скина' },
        { status: 400 }
      );
    }

    // Получаем токен из cookie
    const cookieStore = cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json(
        { success: false, message: 'Необходима авторизация' },
        { status: 401 }
      );
    }

    // Получаем пользователя по токену
    const [user] = await query<any>(`
      SELECT id, steam_id
      FROM user
      WHERE auth_key = ? AND status = 1
    `, [authToken]);

    if (!user) {
      return NextResponse.json(
        { success: false, message: 'Пользователь не найден' },
        { status: 401 }
      );
    }

    // Получаем данные скина
    const [skin] = await query<any>(`
      SELECT 
        id,
        class_id,
        instance_id,
        game_type,
        market_hash_name,
        name,
        ru_name,
        image_url,
        image300_url,
        our_price / 100.0 as our_price,
        status
      FROM market_skins
      WHERE id = ? AND status = 1
    `, [skinId]);

    if (!skin) {
      return NextResponse.json(
        { success: false, message: 'Скин не найден или недоступен' },
        { status: 404 }
      );
    }

    // Получаем или создаем баланс скинов пользователя
    let [balance] = await query<any>(`
      SELECT id, balance
      FROM user_balance
      WHERE user_id = ? AND type = ?
    `, [user.id, TYPE_SKINS]);

    if (!balance) {
      await conn.execute(`
        INSERT INTO user_balance (user_id, type, balance, created_at)
        VALUES (?, ?, 0, NOW())
      `, [user.id, TYPE_SKINS]);
      
      [balance] = await query<any>(`
        SELECT id, balance
        FROM user_balance
        WHERE user_id = ? AND type = ?
      `, [user.id, TYPE_SKINS]);
    }

    // Проверяем баланс (округляем вверх для сравнения)
    const balanceCeil = Math.ceil(balance.balance);
    const skinPrice = Math.ceil(skin.our_price);
    
    if (skinPrice > balanceCeil) {
      return NextResponse.json(
        { success: false, message: 'Недостаточно средств на счету!' },
        { status: 400 }
      );
    }

    // Начинаем транзакцию
    await conn.beginTransaction();

    try {
      // Создаем запись о выплате скина
      const [result] = await conn.execute(`
        INSERT INTO user_payout_skins (
          user_id,
          name,
          image,
          image300,
          type,
          status,
          amount,
          created_at
        ) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
      `, [
        user.id,
        skin.name,
        skin.image_url,
        skin.image300_url,
        skin.game_type,
        skinPrice,
      ]);

      const payoutId = (result as any).insertId;

      // TODO: Здесь нужно вызвать покупку скина через API rustTm или csGoMarket
      // Пока что просто создаем запись со статусом WAIT
      // В реальной реализации здесь должен быть вызов:
      // - Yii::$app->rustTm->buy() для Rust
      // - Yii::$app->csGoMarket->buy() для CS2
      
      // Для демонстрации устанавливаем статус WAIT
      // В продакшене здесь будет:
      // 1. Получение trade_link пользователя
      // 2. Вызов API покупки
      // 3. Сохранение skin_id и price из ответа API
      
      // Пересчитываем баланс
      await conn.execute(`
        UPDATE user_balance 
        SET balance = (
          SELECT COALESCE(SUM(amount), 0)
          FROM profit
          WHERE user_balance_id = ?
        ) - (
          SELECT COALESCE(SUM(amount), 0)
          FROM user_payout_skins
          WHERE user_id = ? AND status IN (0, 1, 2)
        ) - (
          SELECT COALESCE(SUM(amount), 0)
          FROM profit
          WHERE user_balance_id = (
            SELECT id FROM user_balance WHERE user_id = ? AND type = 1
          ) AND type = 7
        )
        WHERE id = ?
      `, [balance.id, user.id, user.id, balance.id]);

      const [newBalance] = await query<any>(`
        SELECT balance
        FROM user_balance
        WHERE id = ?
      `, [balance.id]);

      await conn.commit();

      return NextResponse.json({
        success: true,
        message: 'Скин отправляется, ожидайте трейд-обмен',
        data: {
          newBalance: Math.ceil(newBalance.balance),
          payoutId,
        },
      });
    } catch (error: any) {
      await conn.rollback();
      console.error('Error purchasing skin:', error);
      throw error;
    }
  } catch (error: any) {
    console.error('Error in skin purchase:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Произошла ошибка при покупке скина' },
      { status: 500 }
    );
  } finally {
    conn.release();
  }
}






