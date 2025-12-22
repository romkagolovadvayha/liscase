import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';

/**
 * Выход пользователя из системы
 */
export async function POST() {
  try {
    const cookieStore = await cookies();
    
    // Удаляем cookie auth_token
    cookieStore.delete('auth_token');
    
    return NextResponse.json({
      success: true,
      message: 'Logged out successfully',
    });
  } catch (error: any) {
    console.error('Error logging out:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}










