import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

export const dynamic = 'force-dynamic';

interface SkinsQueryParams {
  page?: string;
  limit?: string;
  category?: string;
  search?: string;
  sort?: string;
  order?: 'asc' | 'desc';
  minPrice?: string;
  maxPrice?: string;
}

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    
    const page = parseInt(searchParams.get('page') || '1');
    const limit = parseInt(searchParams.get('limit') || '50');
    const category = searchParams.get('category');
    const search = searchParams.get('search');
    const gameType = searchParams.get('gameType');
    const sort = searchParams.get('sort') || 'our_price';
    const order = (searchParams.get('order') || 'asc') as 'asc' | 'desc';
    const minPrice = searchParams.get('minPrice');
    const maxPrice = searchParams.get('maxPrice');

    const offset = (page - 1) * limit;

    // Построение запроса
    let whereConditions: string[] = ['status = 1'];
    const params: any[] = [];

    if (gameType && (gameType === 'rust' || gameType === 'cs2')) {
      whereConditions.push('game_type = ?');
      params.push(gameType);
    }

    if (category) {
      whereConditions.push('category = ?');
      params.push(category);
    }

    if (search) {
      whereConditions.push('(name LIKE ? OR ru_name LIKE ? OR market_hash_name LIKE ?)');
      const searchPattern = `%${search}%`;
      params.push(searchPattern, searchPattern, searchPattern);
    }

    if (minPrice) {
      whereConditions.push('our_price >= ?');
      params.push(Math.ceil(parseFloat(minPrice) * 100)); // конвертируем в копейки
    }

    if (maxPrice) {
      whereConditions.push('our_price <= ?');
      params.push(Math.ceil(parseFloat(maxPrice) * 100)); // конвертируем в копейки
    }

    const whereClause = whereConditions.length > 0 
      ? 'WHERE ' + whereConditions.join(' AND ')
      : '';

    // Валидация сортировки
    const allowedSorts = ['our_price', 'name', 'ru_name', 'popularity_7d', 'created_at', 'updated_at'];
    const sortField = allowedSorts.includes(sort) ? sort : 'our_price';
    const sortOrder = order.toLowerCase() === 'desc' ? 'DESC' : 'ASC';

    // Подсчет общего количества
    const [countResult] = await query<any>(`
      SELECT COUNT(*) as total
      FROM market_skins
      ${whereClause}
    `, params);
    const total = countResult?.total || 0;

    // Получение данных
    const skins = await query<any>(`
      SELECT 
        id,
        class_id,
        instance_id,
        game_type,
        market_hash_name,
        name,
        ru_name,
        category,
        ru_quality,
        text_color,
        bg_color,
        price / 100.0 as price,
        our_price / 100.0 as our_price,
        markup_percent,
        avg_price / 100.0 as avg_price,
        popularity_7d,
        image_url,
        image300_url,
        is_stat_trak,
        last_synced_at,
        created_at,
        updated_at
      FROM market_skins
      ${whereClause}
      ORDER BY ${sortField} ${sortOrder}
      LIMIT ? OFFSET ?
    `, [...params, limit, offset]);

    return NextResponse.json({
      success: true,
      data: {
        items: skins,
        pagination: {
          page,
          limit,
          total,
          totalPages: Math.ceil(total / limit),
        },
      },
    });
  } catch (error: any) {
    console.error('Error fetching skins:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при получении списка скинов' },
      { status: 500 }
    );
  }
}

