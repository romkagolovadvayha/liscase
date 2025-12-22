import { NextResponse } from 'next/server';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';
import { formatImageUrl } from '@/lib/utils/imageUrl';

export async function GET() {
  try {
    // Получаем настройки для CDN URL (только для продуктов)
    const settings = await getSettings();
    const cdnUrl = (settings.site_cdnUrl as string) || '';

    // Получаем категории
    const categoriesRaw = await query<any>(`
      SELECT 
        id,
        name,
        image,
        sort
      FROM category
      ORDER BY sort ASC, name ASC
    `);

    // Категории без CDN - используем изображения как есть
    const categories = categoriesRaw.map((category: any) => ({
      ...category,
      image: category.image || undefined,
    }));

    // Получаем товары для главной страницы
    const productsRaw = await query<any>(`
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
        di.image,
        c.name as category_name
      FROM \`drop\` d
      LEFT JOIN drop_image di ON d.id = di.drop_id AND di.type = 4
      LEFT JOIN category c ON d.category_id = c.id
      WHERE d.status = 1 AND d.market_status = 1
      ORDER BY d.sort ASC, d.id DESC
      LIMIT 50
    `);

    // Форматируем URL изображений продуктов с учетом CDN
    const products = productsRaw.map((product: any) => ({
      ...product,
      image: formatImageUrl(product.image, cdnUrl),
    }));

    // Получаем серверы
    const servers = await query<any>(`
      SELECT 
        id,
        name,
        tag,
        ip,
        port,
        players,
        joined,
        queued,
        max,
        status,
        wipe_type,
        next_wipe,
        description
      FROM servers
      WHERE status IN (1, 2)
      ORDER BY sort ASC, id ASC
    `);

    // Получаем статистику проекта
    const [onlineStats] = await query<{ total_online: number; total_users: number }>(`
      SELECT 
        COALESCE(SUM(CASE WHEN status = 1 THEN players ELSE 0 END), 0) as total_online,
        (SELECT COUNT(*) FROM user WHERE status = 1) as total_users
      FROM servers
    `);

    return NextResponse.json({
      success: true,
      data: {
        categories,
        products,
        servers,
        projectStats: {
          online: onlineStats?.total_online || 0,
          users: onlineStats?.total_users || 0,
        },
      },
    });
  } catch (error: any) {
    console.error('Error fetching homepage data:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}

