import { NextResponse } from 'next/server';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';
import { formatImageUrl } from '@/lib/utils/imageUrl';

export const dynamic = 'force-dynamic';

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const categoryId = searchParams.get('category_id');
    const search = searchParams.get('search');
    const limit = parseInt(searchParams.get('limit') || '50');
    const offset = parseInt(searchParams.get('offset') || '0');

    let sql = `
      SELECT 
        d.id,
        d.name,
        d.eng_name,
        d.price,
        d.count,
        d.discount,
        d.category_id,
        d.status,
        d.drop_type,
        d.blocked_hour,
        d.blocked_at,
        d.description,
        d.floating_price_percent,
        di.image,
        c.name as category_name
      FROM \`drop\` d
      LEFT JOIN drop_image di ON d.id = di.drop_id AND di.type = 4
      LEFT JOIN category c ON d.category_id = c.id
      WHERE d.status = 1 AND d.market_status = 1
    `;

    const params: any[] = [];

    if (categoryId && categoryId !== '0') {
      const categoryIdNum = parseInt(String(categoryId), 10);
      if (!isNaN(categoryIdNum)) {
        sql += ' AND d.category_id = ?';
        params.push(categoryIdNum);
      }
    }

    if (search) {
      sql += ' AND (d.name LIKE ? OR d.eng_name LIKE ?)';
      params.push(`%${search}%`, `%${search}%`);
    }

    // Убеждаемся, что limit и offset - целые числа
    const limitValue = Math.max(1, parseInt(String(limit), 10));
    const offsetValue = Math.max(0, parseInt(String(offset), 10));
    
    // Используем template literal для LIMIT и OFFSET, чтобы избежать проблем с параметрами
    sql += ` ORDER BY d.sort ASC, d.id DESC LIMIT ${limitValue} OFFSET ${offsetValue}`;

    const productsRaw = await query<any>(sql, params);

    // Получаем CDN URL из настроек
    const settings = await getSettings();
    const cdnUrl = (settings.site_cdnUrl as string) || '';

    // Получаем ID продуктов, которым нужны subDrops (TYPE_SET = 2, TYPE_SELECT = 3)
    const productsWithSubDrops = productsRaw
      .filter((p: any) => p.drop_type === 2 || p.drop_type === 3)
      .map((p: any) => p.id);

    // Загружаем subDrops для нужных продуктов
    let subDropsMap: Record<number, any[]> = {};
    if (productsWithSubDrops.length > 0) {
      const subDropsRaw = await query<any>(`
        SELECT 
          dd.id,
          dd.drop_id,
          dd.parent_drop_id,
          dd.count,
          d.name,
          d.price,
          d.description,
          di.image
        FROM drop_drop dd
        INNER JOIN \`drop\` d ON dd.drop_id = d.id
        LEFT JOIN drop_image di ON d.id = di.drop_id AND di.type = 4
        WHERE dd.parent_drop_id IN (${productsWithSubDrops.map(() => '?').join(',')})
        ORDER BY dd.parent_drop_id ASC, dd.id ASC
      `, productsWithSubDrops);

      // Группируем subDrops по parent_drop_id
      subDropsRaw.forEach((subDrop: any) => {
        if (!subDropsMap[subDrop.parent_drop_id]) {
          subDropsMap[subDrop.parent_drop_id] = [];
        }
        subDropsMap[subDrop.parent_drop_id].push({
          ...subDrop,
          image: formatImageUrl(subDrop.image, cdnUrl),
        });
      });
    }

    // Форматируем URL изображений с учетом CDN и добавляем priceReal и subDrops
    const products = productsRaw.map((product: any) => {
      const priceReal = product.discount > 0 
        ? product.price * (1 - product.discount / 100)
        : product.price;

      return {
        ...product,
        image: formatImageUrl(product.image, cdnUrl),
        priceReal: Math.ceil(priceReal),
        subDrops: subDropsMap[product.id] || undefined,
      };
    });

    // Получаем общее количество для пагинации
    let countSql = `
      SELECT COUNT(*) as total
      FROM \`drop\` d
      WHERE d.status = 1 AND d.market_status = 1
    `;
    const countParams: any[] = [];

    if (categoryId && categoryId !== '0') {
      const categoryIdNum = parseInt(String(categoryId), 10);
      if (!isNaN(categoryIdNum)) {
        countSql += ' AND d.category_id = ?';
        countParams.push(categoryIdNum);
      }
    }

    if (search) {
      countSql += ' AND (d.name LIKE ? OR d.eng_name LIKE ?)';
      countParams.push(`%${search}%`, `%${search}%`);
    }

    const [countResult] = await query<{ total: number }>(countSql, countParams);
    const total = countResult?.total || 0;

    return NextResponse.json({
      success: true,
      data: products,
      pagination: {
        total,
        limit,
        offset,
        pageCount: Math.ceil(total / limit),
      },
    });
  } catch (error: any) {
    console.error('Error fetching products:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}

