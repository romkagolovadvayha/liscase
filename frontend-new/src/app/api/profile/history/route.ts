import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';

export const dynamic = 'force-dynamic';

/**
 * Получение истории операций пользователя
 */
export async function GET(request: NextRequest) {
  const searchParams = request.nextUrl.searchParams;
  const page = parseInt(searchParams.get('page') || '1', 10);
  const pageSize = parseInt(searchParams.get('pageSize') || '20', 10);
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
    }>(`
      SELECT id
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

    // Получаем profits из всех балансов пользователя
    const profits = await query<{
      comment: string;
      amount: number;
      created_at: string;
    }>(`
      SELECT 
        p.comment,
        p.amount,
        p.created_at
      FROM profit p
      INNER JOIN user_balance ub ON p.user_balance_id = ub.id
      WHERE ub.user_id = ? AND p.status = 1
      ORDER BY p.created_at DESC
    `, [user.id]);

    // Получаем invoices
    const invoices = await query<{
      comment: string;
      amount: number;
      created_at: string;
    }>(`
      SELECT 
        comment,
        amount,
        created_at
      FROM invoice
      WHERE user_id = ?
      ORDER BY created_at DESC
    `, [user.id]);

    // Получаем deposits (только успешные)
    const deposits = await query<{
      amount: number;
      created_at: string;
    }>(`
      SELECT 
        amount,
        created_at
      FROM deposit
      WHERE user_id = ? AND status = 2
      ORDER BY created_at DESC
    `, [user.id]);

    // Формируем общий список
    const list: Array<{
      comment: string;
      sum: string;
      created_at: string;
    }> = [];

    // Добавляем profits
    profits.forEach(profit => {
      list.push({
        comment: profit.comment,
        sum: `+${profit.amount.toLocaleString('ru-RU')}`,
        created_at: profit.created_at,
      });
    });

    // Добавляем invoices
    invoices.forEach(invoice => {
      list.push({
        comment: invoice.comment,
        sum: `-${invoice.amount.toLocaleString('ru-RU')}`,
        created_at: invoice.created_at,
      });
    });

    // Добавляем deposits
    deposits.forEach(deposit => {
      list.push({
        comment: 'Пополнение баланса',
        sum: `+${deposit.amount.toLocaleString('ru-RU')}`,
        created_at: deposit.created_at,
      });
    });

    // Получаем параметры сортировки и фильтрации
    const sortField = searchParams.get('sort') || 'created_at';
    const sortOrder = searchParams.get('order') || 'desc';
    const filterType = searchParams.get('filterType') || 'all';

    // Фильтруем данные по типу операции
    let filteredList = list;
    if (filterType === 'debit') {
      // Только списания (отрицательные суммы)
      filteredList = list.filter(item => item.sum.startsWith('-'));
    } else if (filterType === 'credit') {
      // Только пополнения (положительные суммы)
      filteredList = list.filter(item => item.sum.startsWith('+'));
    }

    // Сортируем данные
    const validSortFields: Record<string, (a: any, b: any) => number> = {
      'comment': (a, b) => {
        const aVal = a.comment.toLowerCase();
        const bVal = b.comment.toLowerCase();
        return sortOrder === 'asc' ? (aVal < bVal ? -1 : aVal > bVal ? 1 : 0) : (aVal > bVal ? -1 : aVal < bVal ? 1 : 0);
      },
      'created_at': (a, b) => {
        const aVal = new Date(a.created_at).getTime();
        const bVal = new Date(b.created_at).getTime();
        return sortOrder === 'asc' ? aVal - bVal : bVal - aVal;
      },
      'sum': (a, b) => {
        const aVal = parseFloat(a.sum.replace(/[+\- ]/g, ''));
        const bVal = parseFloat(b.sum.replace(/[+\- ]/g, ''));
        return sortOrder === 'asc' ? aVal - bVal : bVal - aVal;
      },
    };

    const sortFunction = validSortFields[sortField] || validSortFields['created_at'];
    filteredList.sort(sortFunction);

    // Пагинация
    const total = filteredList.length;
    const totalPages = Math.ceil(total / pageSize);
    const offset = (page - 1) * pageSize;
    const paginatedList = filteredList.slice(offset, offset + pageSize);

    return NextResponse.json({
      success: true,
      history: paginatedList,
      pagination: {
        page,
        pageSize,
        totalPages,
        total,
      },
    });
  } catch (error: any) {
    console.error('Error fetching history:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}

