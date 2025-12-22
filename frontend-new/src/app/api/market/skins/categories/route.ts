import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET() {
  try {
    const categories = await query<any>(`
      SELECT 
        category,
        COUNT(*) as count
      FROM market_skins
      WHERE status = 1 AND category IS NOT NULL
      GROUP BY category
      ORDER BY category ASC
    `);

    return NextResponse.json({
      success: true,
      data: categories.map((c: any) => ({
        name: c.category,
        count: c.count,
      })),
    });
  } catch (error: any) {
    console.error('Error fetching categories:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при получении категорий' },
      { status: 500 }
    );
  }
}






