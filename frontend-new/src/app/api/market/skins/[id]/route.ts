import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET(
  request: Request,
  { params }: { params: { id: string } }
) {
  try {
    const id = parseInt(params.id);

    if (isNaN(id)) {
      return NextResponse.json(
        { success: false, message: 'Неверный ID скина' },
        { status: 400 }
      );
    }

    const [skin] = await query<any>(`
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
        status,
        last_synced_at,
        created_at,
        updated_at
      FROM market_skins
      WHERE id = ? AND status = 1
    `, [id]);

    if (!skin) {
      return NextResponse.json(
        { success: false, message: 'Скин не найден' },
        { status: 404 }
      );
    }

    return NextResponse.json({
      success: true,
      data: skin,
    });
  } catch (error: any) {
    console.error('Error fetching skin:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при получении скина' },
      { status: 500 }
    );
  }
}






