import { NextResponse } from 'next/server';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';
import { formatImageUrl } from '@/lib/utils/imageUrl';

export async function GET(
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

    // Получаем основную информацию о продукте
    const [product] = await query<any>(`
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
      WHERE d.id = ?
    `, [productId]);

    if (!product) {
      return NextResponse.json(
        { success: false, message: 'Product not found' },
        { status: 404 }
      );
    }

    // Получаем subDrops (для наборов и товаров с выбором)
    const subDrops = await query<any>(`
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
      WHERE dd.parent_drop_id = ?
      ORDER BY dd.id ASC
    `, [productId]);

    // Получаем CDN URL из настроек
    const settings = await getSettings();
    const cdnUrl = (settings.site_cdnUrl as string) || '';

    // Форматируем данные
    const formattedProduct = {
      ...product,
      image: formatImageUrl(product.image, cdnUrl),
      subDrops: subDrops.map((subDrop: any) => ({
        ...subDrop,
        image: formatImageUrl(subDrop.image, cdnUrl),
      })),
    };

    // Вычисляем реальную цену с учетом скидки
    const priceReal = product.discount > 0 
      ? product.price * (1 - product.discount / 100)
      : product.price;

    return NextResponse.json({
      success: true,
      data: {
        ...formattedProduct,
        priceReal: Math.ceil(priceReal),
      },
    });
  } catch (error: any) {
    console.error('Error fetching product:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}

