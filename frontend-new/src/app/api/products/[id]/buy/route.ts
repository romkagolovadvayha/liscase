import { NextResponse } from 'next/server';
import { getDbConnection, query } from '@/lib/db';
import { cookies } from 'next/headers';

const TYPE_PAYMENT_MARKET_DROP = 2;

export async function POST(
  request: Request,
  { params }: { params: { id: string } }
) {
  try {
    const productId = parseInt(params.id);
    
    if (isNaN(productId)) {
      return NextResponse.json(
        { success: false, message: 'Invalid product ID' },
        { status: 400 }
      );
    }

    const body = await request.json();
    const quantity = body.quantity || 1;
    const dropId = body.drop_id; // Для товаров с выбором (TYPE_SELECT)

    // Получаем токен из cookie
    const cookieStore = cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json(
        { success: false, message: 'Unauthorized' },
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
        { success: false, message: 'User not found' },
        { status: 401 }
      );
    }

    // Получаем продукт
    const [product] = await query<any>(`
      SELECT 
        id,
        name,
        price,
        discount,
        count,
        drop_type
      FROM \`drop\`
      WHERE id = ? AND status = 1 AND market_status = 1
    `, [productId]);

    if (!product) {
      return NextResponse.json(
        { success: false, message: 'Product not found' },
        { status: 404 }
      );
    }

    // Для товаров с выбором (TYPE_SELECT = 3) нужно получить цену выбранного варианта
    let priceToUse = product.price;
    let discountToUse = product.discount;
    
    if (product.drop_type === 3) {
      // Для TYPE_SELECT обязательно должен быть указан dropId
      if (!dropId) {
        return NextResponse.json(
          { success: false, message: 'Необходимо выбрать вариант товара' },
          { status: 400 }
        );
      }
      
      // Проверяем, что выбранный drop связан с родительским продуктом, и получаем его цену
      const [selectedDrop] = await query<any>(`
        SELECT d.price, d.discount
        FROM drop_drop dd
        INNER JOIN \`drop\` d ON dd.drop_id = d.id
        WHERE dd.parent_drop_id = ? AND dd.drop_id = ? AND d.status = 1
        LIMIT 1
      `, [productId, dropId]);
      
      if (!selectedDrop) {
        return NextResponse.json(
          { success: false, message: 'Выбранный вариант товара не найден или недоступен' },
          { status: 404 }
        );
      }
      
      priceToUse = selectedDrop.price;
      discountToUse = selectedDrop.discount || 0;
    }

    // Вычисляем реальную цену
    const priceReal = discountToUse > 0 
      ? priceToUse * (1 - discountToUse / 100)
      : priceToUse;
    
    const totalPrice = Math.ceil(priceReal * quantity);
    
    // Логирование для отладки
    console.log('[Buy API] Price calculation:', {
      productId,
      dropId,
      dropType: product.drop_type,
      priceToUse,
      discountToUse,
      priceReal,
      quantity,
      totalPrice,
    });

    // Проверяем баланс
    const [balance] = await query<any>(`
      SELECT balance
      FROM user_balance
      WHERE user_id = ? AND type = 1
    `, [user.id]);

    const userBalance = balance?.balance || 0;

    if (totalPrice > userBalance) {
      return NextResponse.json(
        { success: false, message: 'Недостаточно средств на счете!' },
        { status: 400 }
      );
    }

    // Начинаем транзакцию
    const conn = await getDbConnection();
    let transactionStarted = false;
    try {
      await conn.beginTransaction();
      transactionStarted = true;

      // Создаем запись в Invoice
      const comment = `Покупка предмета "${product.name}"`;
      await conn.execute(`
        INSERT INTO invoice (user_id, amount, type, drop_id, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
      `, [user.id, totalPrice, TYPE_PAYMENT_MARKET_DROP, dropId || productId, comment]);

      // Обновляем баланс
      await conn.execute(`
        UPDATE user_balance
        SET balance = balance - ?
        WHERE user_id = ? AND type = 1
      `, [totalPrice, user.id]);

      // Создаем запись в user_drop
      // Для TYPE_SELECT (3) используем выбранный drop_id
      // Для TYPE_SET (2) создаем записи для всех subDrops с sets_id
      // Для остальных типов создаем запись для основного продукта

      if (product.drop_type === 3) {
        // Товар с выбором - используем выбранный вариант
        if (!dropId) {
          throw new Error('Необходимо выбрать вариант товара');
        }
        const [selectedDrops] = await conn.execute<any>(`
          SELECT drop_id, count
          FROM drop_drop
          WHERE parent_drop_id = ? AND drop_id = ?
          LIMIT 1
        `, [productId, dropId]) as any[];

        if (!selectedDrops || selectedDrops.length === 0) {
          throw new Error('Выбранный вариант товара не найден');
        }

        const finalCount = selectedDrops[0].count || 1;
        // Для quantity > 1 создаем несколько записей
        for (let i = 0; i < quantity; i++) {
          await conn.execute(`
            INSERT INTO user_drop (user_id, drop_id, parent_drop_id, status, count, created_at)
            VALUES (?, ?, ?, 1, ?, NOW())
          `, [user.id, dropId, productId, finalCount]);
        }
      } else if (product.drop_type === 2) {
        // Набор - создаем записи для всех subDrops с sets_id = productId
        const [subDropsResult] = await conn.execute<any>(`
          SELECT drop_id, count
          FROM drop_drop
          WHERE parent_drop_id = ?
        `, [productId]) as any[];

        if (!subDropsResult || subDropsResult.length === 0) {
          throw new Error('Набор не содержит предметов');
        }

        // Для каждого количества создаем наборы
        for (let i = 0; i < quantity; i++) {
          for (const subDrop of subDropsResult) {
            await conn.execute(`
              INSERT INTO user_drop (user_id, drop_id, sets_id, status, count, created_at)
              VALUES (?, ?, ?, 1, ?, NOW())
            `, [user.id, subDrop.drop_id, productId, subDrop.count || 1]);
          }
        }
      } else {
        // Обычный товар - создаем отдельные записи для каждого quantity
        const finalDropId = productId;
        const finalCount = product.count || 1;

        // Для quantity > 1 создаем несколько записей
        for (let i = 0; i < quantity; i++) {
          await conn.execute(`
            INSERT INTO user_drop (user_id, drop_id, status, count, created_at)
            VALUES (?, ?, 1, ?, NOW())
          `, [user.id, finalDropId, finalCount]);
        }
      }

      await conn.commit();

      // Получаем обновленный баланс (используем существующее соединение)
      const [balanceRows] = await conn.execute<any>(`
        SELECT balance
        FROM user_balance
        WHERE user_id = ? AND type = 1
      `, [user.id]) as any[];
      
      const finalBalance = balanceRows?.[0]?.balance || 0;

      // Отправляем обновление баланса через WebSocket сервер
      // Уведомляем WebSocket сервер об обновлении баланса через HTTP
      // (WebSocket сервер работает в отдельном процессе, поэтому используем HTTP)
      try {
        const balanceStr = new Intl.NumberFormat('ru-RU').format(finalBalance);
        const wsApiUrl = process.env.WS_API_URL || 'http://localhost:4888';
        
        const wsResponse = await fetch(`${wsApiUrl}/api/balance-update`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            user_id: user.id,
            balance: finalBalance,
            balanceStr: balanceStr,
          }),
        });

        if (!wsResponse.ok) {
          const errorText = await wsResponse.text();
          console.error('[Buy API] Failed to notify WebSocket server:', {
            status: wsResponse.status,
            statusText: wsResponse.statusText,
            error: errorText,
          });
        } else {
          console.log('[Buy API] WebSocket server notified successfully');
        }
      } catch (error: any) {
        // Игнорируем ошибки WebSocket - не блокируем покупку
        console.error('[Buy API] Error notifying WebSocket server:', {
          message: error.message,
        });
      }

      return NextResponse.json({
        success: true,
        message: 'Предмет успешно приобретен!',
        data: {
          newBalance: finalBalance,
        },
      });
    } catch (error: any) {
      if (transactionStarted) {
        await conn.rollback();
      }
      throw error;
    } finally {
      conn.release();
    }
  } catch (error: any) {
    console.error('Error buying product:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Произошла ошибка при оплате!' },
      { status: 500 }
    );
  }
}

