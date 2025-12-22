import { NextRequest, NextResponse } from 'next/server';
import { getPlayerProfileData } from '@/lib/profile';

export const revalidate = 3600; // Кешировать на 1 час

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ steamId: string }> }
) {
  try {
    const { steamId } = await params;

    if (!steamId) {
      return NextResponse.json(
        { success: false, message: 'Steam ID is required' },
        { status: 400 }
      );
    }

    const data = await getPlayerProfileData(steamId);

    if (!data) {
      return NextResponse.json(
        { success: false, message: 'Пользователь не найден' },
        { status: 404 }
      );
    }

    return NextResponse.json({
      success: true,
      data,
    });
  } catch (error: any) {
    console.error('Error in profile API:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при загрузке профиля' },
      { status: 500 }
    );
  }
}
